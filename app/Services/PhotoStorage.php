<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Photo;
use Naluz\Database\Connection;
use Naluz\Storage\StorageManager;
use Naluz\Storage\Uploads;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores uploads on DigitalOcean Spaces when it is configured, otherwise on the local `public` disk (public/storage, for
 * development). Every account has its own folder in the bucket, named by its id, and files get random names inside it:
 *
 *   <folder>/users/<user id>/photos/<random>.<ext>    page photos (shared by all of the account's pages)
 *   <folder>/users/<user id>/avatar/<random>.<ext>    the profile photo
 *
 * The database keeps only the disk and that key (photos.disk/path, users.avatar_disk/avatar_path), never a URL: url() makes
 * the address at render time (the CDN's, when SPACES_CDN_URL is set), so moving to another CDN or domain needs no data change.
 *
 * Deleting is two steps, so no file outlives its photo: trash() runs inside the transaction that deletes the photo
 * rows and remembers their files (table storage_trash); purge() deletes those files once it has committed. A file
 * that could not be deleted stays in the trash for bin/empty-trash.php. A file another photo still uses is kept.
 */
final class PhotoStorage
{
    public function __construct(
        private readonly StorageManager $storage,
        private readonly Connection $db,
        private readonly string $folder,
        private readonly int $maxBytes,
    ) {
    }

    public function diskName(): string
    {
        try {
            $this->storage->disk('spaces');
            return 'spaces';
        } catch (\InvalidArgumentException) {
            return 'public';
        }
    }

    public function maxBytes(): int
    {
        return $this->maxBytes;
    }

    /** "10 MB", "14.2 MB": counted in 1024 × 1024 bytes, like phones and computers show file sizes. */
    public static function megabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
    }

    /** A page photo, filed in the folder of the account that owns the page. */
    public function store(UploadedFileInterface $file, Page $page): array
    {
        return $this->storeFor($file, (int) $page->user_id, 'photos');
    }

    /**
     * @param string $kind the folder inside the account's: photos or avatar
     * @return array{disk:string,path:string,url:string} `disk` and `path` are what to save; `url` is only for the response
     * @throws \Naluz\Storage\StorageException when the file is not an allowed image or the upload fails
     */
    public function storeFor(UploadedFileInterface $file, int $userId, string $kind, ?int $maxBytes = null): array
    {
        $diskName = $this->diskName();
        $disk = $this->storage->disk($diskName);
        $directory = ($this->folder !== '' ? $this->folder . '/' : '') . 'users/' . $userId . '/' . $kind;
        $path = Uploads::store($file, $disk, $directory, Uploads::IMAGES, $maxBytes ?? $this->maxBytes);
        return ['disk' => $diskName, 'path' => $path, 'url' => $disk->url($path)];
    }

    /** The address of a stored file, from its disk and key (the CDN's when there is one); null when the key is empty. */
    public function url(?string $disk, ?string $path): ?string
    {
        if ($disk === null || $disk === '' || $path === null || $path === '') {
            return null;
        }
        try {
            return $this->storage->disk($disk)->url($path);
        } catch (\InvalidArgumentException) {
            return null; // a disk that is not set up here (spaces keys removed): the caller falls back to the legacy URL
        }
    }

    /** What an <img> shows for a photo: its stored file's address, or the URL an imported photo came with. */
    public function photoUrl(Photo $photo): string
    {
        return $this->url($photo->disk, $photo->path) ?? (string) $photo->url;
    }

    /** Call inside the transaction that deletes these photos' rows: their files are remembered for purge(). */
    public function trash(Photo ...$photos): void
    {
        foreach ($photos as $photo) {
            if (self::hasFile($photo)) {
                $this->remember((string) $photo->disk, (string) $photo->path);
            }
        }
    }

    /**
     * Deletes the trashed files of these photos, once the transaction that deleted their rows has committed.
     *
     * @return array{deleted:int,shared:int,failed:int}
     */
    public function purge(Photo ...$photos): array
    {
        $rows = [];
        foreach ($photos as $photo) {
            if (self::hasFile($photo)) {
                $found = $this->db->table('storage_trash')->useWritePdo()
                    ->where('disk', '=', $photo->disk)->where('path', '=', $photo->path)->get()->all();
                foreach ($found as $row) {
                    $rows[(int) $row['id']] = $row; // two photos of one file: its trash rows once
                }
            }
        }
        return $this->process(array_values($rows));
    }

    /** A stored file no photo row was saved for (the save failed): deleted now, or left in the trash to retry. */
    public function discard(string $disk, string $path): void
    {
        $this->remember($disk, $path);
        $this->process($this->db->table('storage_trash')->useWritePdo()->where('disk', '=', $disk)->where('path', '=', $path)->get()->all());
    }

    /**
     * Retries every file left in the trash, once each, oldest first (bin/empty-trash.php).
     *
     * @return array{deleted:int,shared:int,failed:int}
     */
    public function emptyTrash(int $chunk = 200): array
    {
        $result = ['deleted' => 0, 'shared' => 0, 'failed' => 0];
        $after = 0;
        do {
            $rows = $this->db->table('storage_trash')->useWritePdo()->where('id', '>', $after)->orderBy('id')->limit($chunk)->get()->all();
            foreach ($this->process($rows) as $key => $n) {
                $result[$key] += $n;
            }
            $after = $rows === [] ? $after : (int) $rows[count($rows) - 1]['id'];
        } while (count($rows) === $chunk);
        return $result;
    }

    private function remember(string $disk, string $path): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('storage_trash')->insert(['disk' => $disk, 'path' => $path, 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @param list<array<string,mixed>> $rows storage_trash rows
     * @return array{deleted:int,shared:int,failed:int}
     */
    private function process(array $rows): array
    {
        $result = ['deleted' => 0, 'shared' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $disk = (string) $row['disk'];
            $path = (string) $row['path'];
            // another photo (on this or another page) still shows this file: it stays
            if (Photo::query()->withoutCache()->useWritePdo()->where('disk', $disk)->where('path', $path)->exists()) {
                $this->forget((int) $row['id']);
                $result['shared']++;
                continue;
            }
            try {
                $files = $this->storage->disk($disk);
                if (!$files->delete($path) && $files->exists($path)) {
                    throw new \RuntimeException('the storage refused to delete it');
                }
                $this->forget((int) $row['id']);
                $result['deleted']++;
            } catch (\Throwable $e) {
                $this->db->table('storage_trash')->where('id', '=', (int) $row['id'])->update([
                    'attempts' => (int) $row['attempts'] + 1,
                    'last_error' => mb_substr($e->getMessage(), 0, 500),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);
                logger()->error('[photos] could not delete {disk}:{path}, kept in the trash to retry: {error}', ['disk' => $disk, 'path' => $path, 'error' => $e->getMessage()]);
                $result['failed']++;
            }
        }
        return $result;
    }

    private function forget(int $id): void
    {
        $this->db->table('storage_trash')->where('id', '=', $id)->delete();
    }

    /** Imported photos (no disk) live elsewhere: they are never deleted from a storage this app does not own. */
    private static function hasFile(Photo $photo): bool
    {
        return $photo->disk !== null && $photo->disk !== '' && $photo->path !== null && $photo->path !== '';
    }
}
