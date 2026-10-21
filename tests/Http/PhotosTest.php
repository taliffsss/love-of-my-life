<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use App\Services\PhotoAllowance;
use App\Services\PhotoStorage;
use Naluz\Storage\Filesystem;
use Naluz\Storage\StorageManager;
use Naluz\Tests\Support\MemoryDisk;
use Naluz\Tests\TestCase;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\ResponseInterface;

final class PhotosTest extends TestCase
{
    private User $owner;
    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
        $this->signIn($this->owner);
        $this->page = $this->makePage($this->owner);
    }

    private function upload(?string $bytes, ?Page $page = null, ?int $size = null, int $error = UPLOAD_ERR_OK, array $headers = []): ResponseInterface
    {
        $page ??= $this->page;
        $request = new ServerRequest('POST', '/dashboard/pages/' . $page->slug . '/photos', ['X-CSRF-TOKEN' => $this->csrf('/'), 'Accept' => 'application/json'] + $headers);
        if ($bytes !== null) {
            $request = $request->withUploadedFiles(['photo' => new UploadedFile(Stream::create($bytes), $size ?? strlen($bytes), $error, 'x.jpg', 'image/jpeg')]);
        }
        return $this->send($request);
    }

    private function photos(Page $page, int $count, string $prefix = 'p'): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->makePhoto($page, "https://cdn.test/{$prefix}{$i}.jpg", $i);
        }
    }

    private function usage(): array
    {
        return $this->app->make(PhotoAllowance::class)->of($this->owner->id);
    }

    private function pack(int $photos, string $status = Order::PAID, ?User $owner = null): Order
    {
        $order = new Order();
        $order->forceFill([
            'reference' => Order::newReference(), 'user_id' => ($owner ?? $this->owner)->id, 'page_id' => null, 'plan' => Order::PHOTO_PACK,
            'period' => 'once', 'photos' => $photos, 'amount' => (int) ($photos / 12 * 10000), 'currency' => 'PHP', 'status' => $status, 'provider' => 'manual',
        ])->save();
        return $order;
    }

    /** Photos need a plan: the page goes live on Sweet (12 photos). */
    private function subscribe(): void
    {
        $this->page->forceFill(['plan' => 'sweet', 'expires_at' => '2099-01-01 00:00:00', 'published_at' => '2026-01-01 00:00:00'])->save();
    }

    public function testAnUploadNeedsAPlanFirst(): void
    {
        $response = $this->upload($this->png());
        $body = $this->decode($response);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('subscription_required', $body['code']);
        $this->assertFalse($body['usage']['subscribed']);
        $this->assertSame(0, Photo::query()->count());
        $this->assertSame([], $this->disk->files, 'nothing reaches storage before a plan');

        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('id="subscribe-dialog"', $html);
        $this->assertDoesNotMatchRegularExpression('#<label class="upload-zone" id="upload-zone"\s+hidden#', $html, 'the zone stays, and opens the dialog');

        $this->subscribe();
        $this->assertSame(201, $this->upload($this->png())->getStatusCode());
    }

    public function testAnUploadIsStoredUnderThePageAndAddedLast(): void
    {
        $this->subscribe();
        $this->makePhoto($this->page, 'https://cdn.test/first.jpg', 1);
        $response = $this->upload($this->png());
        $body = $this->decode($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(2, $body['count']);
        $this->assertSame(['used' => 2, 'total' => 12, 'remaining' => 10, 'over' => 0, 'packs_needed' => 0, 'subscribed' => true], $body['usage']);
        $this->assertMatchesRegularExpression('#^https://cdn\.test/users/' . $this->page->user_id . '/photos/[0-9a-f]{32}\.png$#', $body['url']);
        $photo = Photo::query()->where('id', $body['id'])->first();
        $this->assertSame('spaces', $photo->disk);
        $this->assertSame(2, $photo->position, 'new photos go to the end');
        $this->assertArrayHasKey($photo->path, $this->disk->files);
    }

    public function testOnlyImagesAreAccepted(): void
    {
        $this->assertSame(422, $this->upload('<?php echo "hi";')->getStatusCode());
        $this->assertSame(422, $this->upload(null)->getStatusCode());
        $this->assertSame(0, Photo::query()->count());
        $this->assertSame([], $this->disk->files);
    }

    public function testPhotosOver10MbAreRefusedWithAClearMessage(): void
    {
        $this->subscribe();
        $response = $this->upload($this->png(), size: 11 * 1024 * 1024);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('This photo is 11 MB. Photos can be up to 10 MB each.', $this->decode($response)['message']);

        $this->assertSame(201, $this->upload($this->png(), size: 10 * 1024 * 1024)->getStatusCode(), 'exactly 10 MB is fine');

        // PHP refused it before the app saw it (over upload_max_filesize)
        $response = $this->upload($this->png(), size: 0, error: UPLOAD_ERR_INI_SIZE);
        $this->assertSame('This photo is too large. Photos can be up to 10 MB each.', $this->decode($response)['message']);
        // over post_max_size: the body arrives without its files
        $response = $this->upload(null, headers: ['Content-Length' => (string) (20 * 1024 * 1024)]);
        $this->assertSame('This photo is 20 MB. Photos can be up to 10 MB each.', $this->decode($response)['message']);

        $this->assertSame(1, Photo::query()->count());
        $this->assertCount(1, $this->disk->files, 'nothing too large was stored');
    }

    public function testEveryPhotoOnEveryPageOfTheAccountCounts(): void
    {
        $this->subscribe();
        $other = $this->makePage($this->owner);
        $this->photos($this->page, 8, 'a');
        $this->photos($other, 4, 'b');
        $this->photos($this->makePage($this->makeUser('other@example.com')), 5, 'c'); // someone else's

        foreach ([$this->page, $other] as $page) {
            $response = $this->upload($this->png(), $page);
            $body = $this->decode($response);
            $this->assertSame(422, $response->getStatusCode(), 'a Sweet plan includes 12 photos for the whole account');
            $this->assertSame('photo_limit', $body['code']);
            $this->assertSame(['used' => 12, 'total' => 12, 'remaining' => 0, 'over' => 0, 'packs_needed' => 1, 'subscribed' => true], $body['usage']);
            $this->assertSame('You have used all 12 photos of your account. Buy 12 more for ₱100 or upgrade a plan to keep adding, or delete one to make room.', $body['message']);
        }
        $this->assertSame(17, Photo::query()->count());
        $this->assertCount(17, $this->disk->files, 'a refused upload is never stored');
        $this->assertSame([], $this->disk->deleted, 'not even for a moment: it is refused before it is uploaded');
    }

    public function testLivePlansAddUpAndPhotoPacksAddMore(): void
    {
        $this->assertSame(6, $this->usage()['total'], 'no live page: the free preview\'s 6');

        $this->page->forceFill(['plan' => 'sweet', 'expires_at' => '2099-01-01 00:00:00', 'published_at' => '2026-01-01 00:00:00'])->save();
        $this->assertSame(12, $this->usage()['total'], 'a live plan replaces the free 6');
        $this->makeLivePage($this->owner, ['plan' => 'sweetheart', 'expires_at' => '2099-01-01 00:00:00', 'recipient' => 'Ben']);
        $usage = $this->usage();
        $this->assertSame([52, 52, 0], [$usage['included'], $usage['total'], $usage['bought']], 'Sweet 12 + Sweetheart 40');
        $this->assertSame([['recipient' => 'Jinelin', 'plan' => 'Sweet', 'photos' => 12], ['recipient' => 'Ben', 'plan' => 'Sweetheart', 'photos' => 40]], $usage['sources']);

        $this->pack(24);
        $this->pack(12, Order::PENDING);
        $this->pack(12, Order::CANCELLED);
        $this->pack(36, Order::PAID, $this->makeUser('other@example.com'));
        $usage = $this->usage();
        $this->assertSame([52, 24, 76], [$usage['included'], $usage['bought'], $usage['total']], 'only this account\'s paid packs count');
    }

    public function testABoughtPackLetsTheNextPhotoIn(): void
    {
        $this->subscribe();
        $this->photos($this->page, 12);
        $this->assertSame(422, $this->upload($this->png())->getStatusCode());
        $this->pack(12);
        $response = $this->upload($this->png());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['used' => 13, 'total' => 24, 'remaining' => 11, 'over' => 0, 'packs_needed' => 0, 'subscribed' => true], $this->decode($response)['usage']);
    }

    public function testAnEndedPlanStopsCountingButNothingIsRemoved(): void
    {
        $this->page->forceFill(['plan' => 'sweetheart', 'expires_at' => '2026-01-01 00:00:00', 'published_at' => '2025-12-01 00:00:00'])->save();
        $this->photos($this->page, 20);

        $response = $this->upload($this->png());
        $body = $this->decode($response);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('subscription_required', $body['code'], 'no live plan any more: subscribe again first');
        $this->assertSame(['used' => 20, 'total' => 6, 'remaining' => 0, 'over' => 14, 'packs_needed' => 2, 'subscribed' => false], $body['usage'], '15 more to fit one: 2 packs');
        $this->assertSame(20, Photo::query()->count(), 'nothing is deleted');
        $this->assertSame([], $this->disk->deleted);

        $html = (string) $this->get('/dashboard/pages/' . $this->page->slug)->getBody();
        $this->assertStringContainsString('14 over', $html);
        $this->assertStringContainsString('A plan ended, so your account holds more photos than it now includes.', $html);
        $this->assertStringContainsString('Subscribe to a plan to add photos.', $html);

        $this->page->forceFill(['expires_at' => '2099-01-01 00:00:00'])->save();
        $this->assertSame(201, $this->upload($this->png())->getStatusCode(), 'renewed: its 40 count again');
    }

    public function testDeletingAPhotoFreesItsPlace(): void
    {
        $this->subscribe();
        $this->photos($this->page, 12);
        $this->assertSame(422, $this->upload($this->png())->getStatusCode());

        $photo = Photo::query()->where('page_id', $this->page->id)->orderBy('id')->first();
        $response = $this->json('DELETE', '/dashboard/pages/' . $this->page->slug . '/photos/' . $photo->id, [], ['X-CSRF-TOKEN' => $this->csrf('/')]);
        $this->assertSame(['used' => 11, 'total' => 12, 'remaining' => 1, 'over' => 0, 'packs_needed' => 0, 'subscribed' => true], $this->decode($response)['usage']);
        $this->assertSame(201, $this->upload($this->png())->getStatusCode());
    }

    public function testTwoUploadsRacingPastTheCheckNeverExceedTheAllowance(): void
    {
        $this->subscribe();
        $other = $this->makePage($this->owner);
        $this->photos($this->page, 11);
        // an upload to another page of the account lands between this request's check and its insert
        $raced = false;
        Photo::creating(function () use (&$raced, $other): void {
            if (!$raced) {
                $raced = true;
                $this->db()->table('photos')->insert(['page_id' => $other->id, 'disk' => null, 'path' => null, 'url' => 'https://cdn.test/other.jpg', 'position' => 1]);
            }
        });

        $response = $this->upload($this->png());
        $this->assertTrue($raced);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('photo_limit', $this->decode($response)['code']);
        $this->assertSame(12, $this->usage()['used'], 'the later photo is undone');
        $this->assertNotEmpty($this->disk->deleted, 'and its file removed');
        $this->assertCount(11, $this->disk->files, 'only the eleven original files remain');
        $this->assertSame(0, $this->db()->table('storage_trash')->count());
    }

    public function testAFileThatCannotBeSavedAsAPhotoIsDeletedAgain(): void
    {
        $this->subscribe();
        Photo::creating(static function (): void {
            throw new \RuntimeException('database is down');
        });
        $this->assertSame(500, $this->upload($this->png())->getStatusCode());
        $this->assertSame(0, Photo::query()->count());
        $this->assertCount(1, $this->disk->deleted, 'the file was stored, then deleted again');
        $this->assertSame([], $this->disk->files, 'so it does not stay behind');
        $this->assertSame(0, $this->db()->table('storage_trash')->count());
    }

    public function testReorderingNeedsEveryPhotoOfThePageOnce(): void
    {
        $a = $this->makePhoto($this->page, 'https://cdn.test/a.jpg', 1);
        $b = $this->makePhoto($this->page, 'https://cdn.test/b.jpg', 2);
        $c = $this->makePhoto($this->page, 'https://cdn.test/c.jpg', 3);
        $url = '/dashboard/pages/' . $this->page->slug . '/photos/order';

        $this->assertSame(422, $this->ajax('POST', $url, ['order' => [$c->id, $a->id]])->getStatusCode(), 'one missing');
        $this->assertSame(422, $this->ajax('POST', $url, ['order' => [$c->id, $a->id, $a->id]])->getStatusCode(), 'one twice');
        $theirs = $this->makePhoto($this->makePage($this->makeUser('other@example.com')), 'https://cdn.test/x.jpg');
        $this->assertSame(422, $this->ajax('POST', $url, ['order' => [$c->id, $a->id, $theirs->id]])->getStatusCode(), 'someone else\'s photo');

        $this->assertSame(200, $this->ajax('POST', $url, ['order' => [$c->id, $a->id, $b->id]])->getStatusCode());
        $order = Photo::query()->where('page_id', $this->page->id)->orderBy('position')->pluck('path')->all();
        $this->assertSame(array_map(fn (string $f): string => 'users/' . $this->page->user_id . '/photos/' . $f, ['c.jpg', 'a.jpg', 'b.jpg']), $order);
        $this->assertSame(1, $theirs->fresh()->position, 'untouched');
    }

    public function testDeletingAPhotoIsSoft(): void
    {
        $photo = $this->makePhoto($this->page, 'https://cdn.test/a.jpg');
        $response = $this->json('DELETE', '/dashboard/pages/' . $this->page->slug . '/photos/' . $photo->id, [], ['X-CSRF-TOKEN' => $this->csrf('/')]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $this->decode($response)['count'], 'hidden from the page');
        $this->assertSame(0, $this->decode($response)['usage']['used'], 'and it stops counting at once');
        $this->assertNull(Photo::query()->find($photo->id));
        $this->assertNotNull($photo->fresh()->getRawAttributes()['deleted_at'], 'the row stays, flagged');
        $this->assertSame([], $this->disk->deleted, 'and so does the file');

        $photo->fresh()->restore();
        $this->assertNotNull(Photo::query()->find($photo->id), 'it can be restored');
        $this->assertSame(1, $this->usage()['used']);
    }

    public function testAFailedStorageDeleteWaitsInTheTrashUntilItWorks(): void
    {
        // the files of an upload that is undone for good (or of an erasure) go through the storage trash, which retries
        $flaky = new FlakyDisk($this->disk);
        $this->app->make(StorageManager::class)->extend('spaces', $flaky);
        $photo = $this->makePhoto($this->page, 'https://cdn.test/a.jpg');
        $storage = $this->app->make(PhotoStorage::class);

        $flaky->down = true;
        $photo->forceDelete();
        $storage->trash($photo);
        $storage->purge($photo);
        $trash = $this->db()->table('storage_trash')->get()->all();
        $this->assertCount(1, $trash);
        $this->assertSame([(string) $photo->path, 1, 'Spaces is unreachable'], [$trash[0]['path'], (int) $trash[0]['attempts'], $trash[0]['last_error']]);

        $this->assertSame(['deleted' => 0, 'shared' => 0, 'failed' => 1], $storage->emptyTrash());
        $flaky->down = false;
        $this->assertSame(['deleted' => 1, 'shared' => 0, 'failed' => 0], $storage->emptyTrash());
        $this->assertSame([], $this->disk->files);
        $this->assertSame(0, $this->db()->table('storage_trash')->count());
    }

    public function testImportedPhotosAreNeverDeletedFromStorageTheyDoNotOwn(): void
    {
        $photo = new Photo();
        $photo->forceFill(['page_id' => $this->page->id, 'disk' => null, 'path' => null, 'url' => '/photos/01.jpg', 'position' => 1])->save();
        $this->json('DELETE', '/dashboard/pages/' . $this->page->slug . '/photos/' . $photo->id, [], ['X-CSRF-TOKEN' => $this->csrf('/')]);
        $this->assertNull(Photo::query()->find($photo->id));
        $this->assertSame([], $this->disk->deleted);
        $this->assertSame(0, $this->db()->table('storage_trash')->count());
    }

    public function testPhotosOfAnotherOwnersPageCannotBeTouched(): void
    {
        $theirs = $this->makePage($this->makeUser('other@example.com'));
        $photo = $this->makePhoto($theirs, 'https://cdn.test/theirs.jpg');

        $this->assertSame(404, $this->upload($this->png(), $theirs)->getStatusCode());
        // their photo id through my page: not found either
        $response = $this->json('DELETE', '/dashboard/pages/' . $this->page->slug . '/photos/' . $photo->id, [], ['X-CSRF-TOKEN' => $this->csrf('/')]);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertNotNull($photo->fresh());
    }
}

/** Spaces that can be "down": deletes fail until it is back. */
final class FlakyDisk implements Filesystem
{
    public bool $down = false;

    public function __construct(private readonly MemoryDisk $disk)
    {
    }

    public function delete(string $path): bool
    {
        if ($this->down) {
            throw new \RuntimeException('Spaces is unreachable');
        }
        return $this->disk->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    public function get(string $path): string
    {
        return $this->disk->get($path);
    }

    public function put(string $path, mixed $contents): void
    {
        $this->disk->put($path, $contents);
    }

    public function copy(string $from, string $to): void
    {
        $this->disk->copy($from, $to);
    }

    public function move(string $from, string $to): void
    {
        $this->disk->move($from, $to);
    }

    public function size(string $path): int
    {
        return $this->disk->size($path);
    }

    public function lastModified(string $path): int
    {
        return $this->disk->lastModified($path);
    }

    public function mimeType(string $path): string
    {
        return $this->disk->mimeType($path);
    }

    public function files(string $directory = ''): array
    {
        return $this->disk->files($directory);
    }

    public function directories(string $directory = ''): array
    {
        return $this->disk->directories($directory);
    }

    public function makeDirectory(string $path): void
    {
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->disk->deleteDirectory($path);
    }

    public function url(string $path): string
    {
        return $this->disk->url($path);
    }
}
