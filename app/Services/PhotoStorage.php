<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Photo;
use Naluz\Storage\StorageManager;
use Naluz\Storage\Uploads;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Stores page photos on DigitalOcean Spaces when it is configured, otherwise on the local `public` disk
 * (public/storage, for development). Files get random names under <folder>/pages/<slug>/.
 */
final class PhotoStorage
{
    public function __construct(private readonly StorageManager $storage, private readonly string $folder, private readonly int $maxBytes)
    {
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

    /**
     * @return array{disk:string,path:string,url:string}
     * @throws \Naluz\Storage\StorageException when the file is not an allowed image or the upload fails
     */
    public function store(UploadedFileInterface $file, Page $page): array
    {
        $diskName = $this->diskName();
        $disk = $this->storage->disk($diskName);
        $directory = ($this->folder !== '' ? $this->folder . '/' : '') . 'pages/' . $page->slug;
        $path = Uploads::store($file, $disk, $directory, Uploads::IMAGES, $this->maxBytes);
        return ['disk' => $diskName, 'path' => $path, 'url' => $disk->url($path)];
    }

    /** Removes the stored file. Imported photos (no disk) are left alone. Failures are logged, never thrown. */
    public function delete(Photo $photo): void
    {
        if ($photo->disk === null || $photo->disk === '' || $photo->path === null || $photo->path === '') {
            return;
        }
        try {
            $this->storage->disk($photo->disk)->delete($photo->path);
        } catch (\Throwable $e) {
            logger()->error('[photos] could not delete {disk}:{path}: {error}', ['disk' => $photo->disk, 'path' => $photo->path, 'error' => $e->getMessage()]);
        }
    }
}
