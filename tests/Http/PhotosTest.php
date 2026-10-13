<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
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

    private function upload(?string $bytes, ?Page $page = null): ResponseInterface
    {
        $page ??= $this->page;
        $request = new ServerRequest('POST', '/dashboard/pages/' . $page->slug . '/photos', ['X-CSRF-TOKEN' => $this->csrf('/'), 'Accept' => 'application/json']);
        if ($bytes !== null) {
            $request = $request->withUploadedFiles(['photo' => new UploadedFile(Stream::create($bytes), strlen($bytes), UPLOAD_ERR_OK, 'x.jpg', 'image/jpeg')]);
        }
        return $this->send($request);
    }

    public function testAnUploadIsStoredUnderThePageAndAddedLast(): void
    {
        $this->makePhoto($this->page, 'https://cdn.test/first.jpg', 1);
        $response = $this->upload($this->png());
        $body = $this->decode($response);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(2, $body['count']);
        $this->assertSame(6, $body['limit']);
        $this->assertMatchesRegularExpression('#^https://cdn\.test/pages/' . $this->page->slug . '/[0-9a-f]{32}\.png$#', $body['url']);
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

    public function testThePlanLimitIsEnforcedOnTheServer(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->makePhoto($this->page, "https://cdn.test/{$i}.jpg", $i);
        }
        $response = $this->upload($this->png());
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('6 photos', $this->decode($response)['message']);
        $this->assertSame(6, Photo::query()->count());

        $this->page->forceFill(['plan' => 'plus', 'expires_at' => '2099-01-01 00:00:00'])->save();
        $this->assertSame(201, $this->upload($this->png())->getStatusCode(), 'Plus allows 20');
    }

    public function testTwoUploadsRacingPastTheCheckNeverExceedTheLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->makePhoto($this->page, "https://cdn.test/{$i}.jpg", $i);
        }
        // a second upload lands between this request's limit check and its insert
        $raced = false;
        Photo::creating(function () use (&$raced): void {
            if (!$raced) {
                $raced = true;
                $this->db()->table('photos')->insert(['page_id' => $this->page->id, 'disk' => null, 'path' => null, 'url' => 'https://cdn.test/other.jpg', 'position' => 6]);
            }
        });

        $response = $this->upload($this->png());
        $this->assertTrue($raced);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(6, Photo::query()->where('page_id', $this->page->id)->count(), 'the later photo is undone');
        $this->assertNotEmpty($this->disk->deleted, 'and its file removed');
        $this->assertCount(5, $this->disk->files, 'only the five original files remain');
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
        $order = Photo::query()->where('page_id', $this->page->id)->orderBy('position')->pluck('url')->all();
        $this->assertSame(['https://cdn.test/c.jpg', 'https://cdn.test/a.jpg', 'https://cdn.test/b.jpg'], $order);
        $this->assertSame(1, $theirs->fresh()->position, 'untouched');
    }

    public function testDeletingAPhotoRemovesTheFile(): void
    {
        $photo = $this->makePhoto($this->page, 'https://cdn.test/a.jpg');
        $response = $this->json('DELETE', '/dashboard/pages/' . $this->page->slug . '/photos/' . $photo->id, [], ['X-CSRF-TOKEN' => $this->csrf('/')]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(0, $this->decode($response)['count']);
        $this->assertNull($photo->fresh());
        $this->assertSame(['pages/' . $this->page->slug . '/a.jpg'], $this->disk->deleted);
    }

    public function testImportedPhotosAreNeverDeletedFromStorageTheyDoNotOwn(): void
    {
        $photo = new Photo();
        $photo->forceFill(['page_id' => $this->page->id, 'disk' => null, 'path' => null, 'url' => '/photos/01.jpg', 'position' => 1])->save();
        $this->json('DELETE', '/dashboard/pages/' . $this->page->slug . '/photos/' . $photo->id, [], ['X-CSRF-TOKEN' => $this->csrf('/')]);
        $this->assertNull($photo->fresh());
        $this->assertSame([], $this->disk->deleted);
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
