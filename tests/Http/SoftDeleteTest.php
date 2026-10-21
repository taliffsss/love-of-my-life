<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use App\Services\AdminStats;
use App\Services\Trash;
use Naluz\Tests\TestCase;

/** Deleting is always soft: what is deleted is hidden and can be restored; only an explicit erase of something already deleted removes it for good. */
final class SoftDeleteTest extends TestCase
{
    private function trash(): Trash
    {
        return $this->app->make(Trash::class);
    }

    public function testRestoringAPageBringsBackOnlyThePhotosDeletedWithIt(): void
    {
        $owner = $this->makeUser();
        $this->signIn($owner);
        $page = $this->makePage($owner);
        $early = $this->makePhoto($page, 'https://cdn.test/early.jpg', 1);
        $late = $this->makePhoto($page, 'https://cdn.test/late.jpg', 2);

        $this->json('DELETE', '/dashboard/pages/' . $page->slug . '/photos/' . $early->id, [], ['X-CSRF-TOKEN' => $this->csrf('/')]);
        $this->assertSame(1, Photo::query()->where('page_id', $page->id)->count());
        sleep(1); // a different second: the page and `late` are deleted together, `early` was deleted earlier
        $this->submit('DELETE', '/dashboard/pages/' . $page->slug, ['confirm' => 'delete']);
        $this->assertNull(Page::query()->find($page->id));

        $this->assertSame(1, $this->trash()->restorePage(Page::query()->onlyTrashed()->find($page->id)));
        $this->assertNotNull(Page::query()->find($page->id), 'the page is back');
        $this->assertSame([$late->id], array_map('intval', Photo::query()->where('page_id', $page->id)->pluck('id')->all()), 'with the photo that went with it, not the earlier one');
        $this->assertSame(200, $this->get('/dashboard/pages/' . $page->slug)->getStatusCode());
    }

    public function testAPageOfADeletedOwnerCannotBeRestoredFirst(): void
    {
        $owner = $this->makeUser();
        $page = $this->makePage($owner);
        $this->db()->table('users')->where('id', '=', $owner->id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        $this->db()->table('pages')->where('id', '=', $page->id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        $this->expectException(\DomainException::class);
        $this->trash()->restorePage(Page::query()->onlyTrashed()->find($page->id));
    }

    public function testErasingOnlyWorksOnSomethingAlreadyDeleted(): void
    {
        $owner = $this->makeUser();
        $page = $this->makePage($owner);
        $photo = $this->makePhoto($page, 'https://cdn.test/a.jpg');
        try {
            $this->trash()->erasePage($page);
            $this->fail('a live page cannot be erased');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('delete it first', $e->getMessage());
        }
        $this->db()->table('pages')->where('id', '=', $page->id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        $this->db()->table('photos')->where('id', '=', $photo->id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        $this->assertSame(1, $this->trash()->erasePage(Page::query()->onlyTrashed()->find($page->id)));
        $this->assertSame(0, Page::query()->withTrashed()->count());
        $this->assertSame(0, Photo::query()->withTrashed()->count());
    }

    public function testADeletedAccountIsToldSoWhenItsPasswordIsRightAndNeverSignsIn(): void
    {
        $user = $this->makeUser('gone@example.com', 'correct horse');
        $this->db()->table('users')->where('id', '=', $user->id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);

        $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'gone@example.com', 'password' => 'correct horse']);
        $html = (string) $this->get('/login')->getBody();
        $this->assertStringContainsString(User::DELETED_MESSAGE, $html);
        $this->assertSame(302, $this->get('/dashboard')->getStatusCode(), 'still signed out');

        $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'gone@example.com', 'password' => 'wrong password']);
        $this->assertStringNotContainsString(User::DELETED_MESSAGE, (string) $this->get('/login')->getBody(), 'a wrong password learns nothing');
    }

    public function testTheAdminNumbersLeaveOutDeletedAccountsAndPages(): void
    {
        $stats = $this->app->make(AdminStats::class);
        $user = $this->makeUser();
        $this->makePage($user);
        $this->makeUser('gone@example.com');
        $gone = User::query()->where('email', 'gone@example.com')->first();
        $this->db()->table('users')->where('id', '=', $gone->id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        $overview = $stats->overview();
        $this->assertSame(1, $overview['users']);
        $this->assertSame(1, $overview['pages']);
    }
}
