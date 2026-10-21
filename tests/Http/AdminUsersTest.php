<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Order;
use App\Models\Page;
use App\Models\Photo;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Naluz\Tests\TestCase;

final class AdminUsersTest extends TestCase
{
    private User $admin;
    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new User();
        $this->admin->forceFill(['name' => 'Boss', 'email' => 'boss@example.com', 'password' => 'correct horse', 'is_admin' => true])->save();
        $this->target = $this->makeUser('target@example.com');
        $this->signIn($this->admin);
    }

    private function setStatus(User $user, string $status, string $reason = ''): void
    {
        $this->submit('POST', '/admin/users/' . $user->id . '/status', ['status' => $status, 'reason' => $reason]);
    }

    private function fresh(User $user): User
    {
        return User::query()->withoutCache()->find($user->id);
    }

    /** Switches the test browser to a different person. */
    private function asVisitor(): void
    {
        $this->cookies = [];
    }

    public function testEveryRowOffersTheActionsAsIcons(): void
    {
        $html = (string) $this->get('/admin/users')->getBody();
        $id = $this->target->id;
        foreach (['edit', 'activate' => false, 'deactivate', 'suspend', 'delete'] as $key => $action) {
            if ($key === 'activate') {
                $this->assertStringNotContainsString('data-user-action="activate" data-user-id="' . $id . '"', $html, 'an active account has nothing to reactivate');
                continue;
            }
            $needle = $action === 'edit' ? 'href="/admin/users/' . $id . '/edit"' : 'data-user-action="' . $action . '" data-user-id="' . $id . '"';
            $this->assertStringContainsString($needle, $html, $action);
        }
        // not for yourself or another administrator: only Edit
        $this->assertStringNotContainsString('data-user-id="' . $this->admin->id . '"', $html);
    }

    public function testEditingChangesNameAndEmailAndChecksThem(): void
    {
        $url = '/admin/users/' . $this->target->id;
        $this->assertStringContainsString('value="target@example.com"', (string) $this->get($url . '/edit')->getBody());

        $this->submit('PUT', $url, ['name' => '  New   Name ', 'email' => 'New@Example.com']);
        $fresh = $this->fresh($this->target);
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('new@example.com', $fresh->email);

        $this->submit('PUT', $url, ['name' => '', 'email' => 'nope']);
        $this->assertSame('new@example.com', $this->fresh($this->target)->email);
        $this->submit('PUT', $url, ['name' => 'X', 'email' => 'boss@example.com']);
        $this->assertSame('new@example.com', $this->fresh($this->target)->email, 'another account has it');
    }

    public function testTheAdministratorRoleCanBeGivenButNotChangedOnYourself(): void
    {
        $this->submit('PUT', '/admin/users/' . $this->target->id, ['name' => 'T', 'email' => 'target@example.com', 'is_admin' => '1']);
        $this->assertTrue($this->fresh($this->target)->is_admin);

        $this->submit('PUT', '/admin/users/' . $this->admin->id, ['name' => 'Boss', 'email' => 'boss@example.com']);
        $this->assertTrue($this->fresh($this->admin)->is_admin, 'cannot remove your own role');
    }

    public function testADeactivatedAccountCannotSignInAndIsSignedOutAtOnce(): void
    {
        $this->asVisitor();
        $this->signIn($this->target);
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode());
        $theirSession = $this->cookies;

        $this->asVisitor();
        $this->signIn($this->admin);
        $this->setStatus($this->target, 'deactivated');
        $this->assertSame('deactivated', $this->fresh($this->target)->status);

        // the open session is ended on its next request
        $this->cookies = $theirSession;
        $this->assertSame('/login', $this->get('/dashboard')->getHeaderLine('Location'));
        $this->assertSame('/login', $this->get('/dashboard')->getHeaderLine('Location'), 'and stays out');

        // the right password is told why; a wrong one is not
        $this->asVisitor();
        $right = $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'target@example.com', 'password' => 'correct horse']);
        $this->assertNotSame('/dashboard', $right->getHeaderLine('Location'));
        $this->assertStringContainsString('deactivated', (string) $this->get('/login')->getBody());
        $this->asVisitor();
        $wrong = $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'target@example.com', 'password' => 'nope nope']);
        $this->assertStringNotContainsString('deactivated', (string) $this->get('/login')->getBody());

        // reactivated: back in
        $this->asVisitor();
        $this->signIn($this->admin);
        $this->setStatus($this->target, 'active');
        $this->asVisitor();
        $this->signIn($this->target);
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode());
    }

    public function testASuspensionShowsItsReason(): void
    {
        $this->setStatus($this->target, 'suspended', 'Spam pages');
        $fresh = $this->fresh($this->target);
        $this->assertSame('suspended', $fresh->status);
        $this->assertSame('Spam pages', $fresh->status_reason);

        $this->asVisitor();
        $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'target@example.com', 'password' => 'correct horse']);
        $html = (string) $this->get('/login')->getBody();
        $this->assertStringContainsString('suspended: Spam pages', $html);

        // a later reactivation forgets the reason
        $this->asVisitor();
        $this->signIn($this->admin);
        $this->setStatus($this->target, 'active');
        $this->assertNull($this->fresh($this->target)->status_reason);
    }

    public function testASuspendedAccountCannotUseGoogleEither(): void
    {
        $user = new User();
        $user->forceFill(['name' => 'G', 'email' => 'g@example.com', 'password' => 'x-long-password-x', 'is_admin' => false, 'login_type' => 'google', 'status' => 'suspended'])->save();
        $link = new SocialAccount();
        $link->forceFill(['user_id' => $user->id, 'provider' => 'google', 'provider_uid' => '55'])->save();

        $answers = [
            new GuzzleResponse(200, [], json_encode(['access_token' => 't'])),
            new GuzzleResponse(200, [], json_encode(['sub' => '55', 'name' => 'G', 'email' => 'g@example.com', 'email_verified' => true])),
        ];
        $this->app->instance(SocialAuth::class, new SocialAuth(
            ['google' => ['client_id' => 'a', 'client_secret' => 'b'], 'facebook' => ['client_id' => '', 'client_secret' => '']],
            'https://love.test',
            new Client(['handler' => HandlerStack::create(new MockHandler($answers)), 'http_errors' => false]),
        ));
        $this->asVisitor();
        $location = $this->get('/auth/google/redirect')->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $q);
        $this->assertSame('/login', $this->get('/auth/google/callback?code=c&state=' . $q['state'])->getHeaderLine('Location'));
        $this->assertStringContainsString('suspended', (string) $this->get('/login')->getBody());
        $this->assertSame('/login', $this->get('/dashboard')->getHeaderLine('Location'));
    }

    public function testYouCannotChangeYourselfOrAnotherAdministratorsStatus(): void
    {
        $this->setStatus($this->admin, 'suspended');
        $this->assertSame('active', $this->fresh($this->admin)->status);
        $this->assertSame(200, $this->get('/admin')->getStatusCode());

        $other = new User();
        $other->forceFill(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'correct horse', 'is_admin' => true])->save();
        $this->setStatus($other, 'deactivated');
        $this->assertSame('active', $this->fresh($other)->status);

        $this->setStatus($this->target, 'banned');
        $this->assertSame('active', $this->fresh($this->target)->status, 'only the three statuses');
    }

    public function testDeletingIsSoftTheAccountItsPagesAndPhotosAreHiddenAndNothingIsErased(): void
    {
        $page = $this->makePage($this->target);
        $this->makePhoto($page, 'https://cdn.test/a.jpg', 1);
        $mine = $this->makePage($this->admin);
        $kept = $this->makePhoto($mine, 'https://cdn.test/kept.jpg');
        $link = new SocialAccount();
        $link->forceFill(['user_id' => $this->target->id, 'provider' => 'google', 'provider_uid' => '1'])->save();
        $pending = new Order();
        $pending->forceFill(['reference' => 'LM-AAAA-BBBB', 'user_id' => $this->target->id, 'page_id' => $page->id, 'plan' => 'sweet', 'period' => 'month', 'amount' => 20000, 'currency' => 'PHP', 'status' => 'pending', 'provider' => 'manual'])->save();

        $url = '/admin/users/' . $this->target->id;
        $this->submit('DELETE', $url, ['confirm' => 'nope']);
        $this->assertNotNull($this->fresh($this->target), 'not without typing delete');

        $response = $this->submit('DELETE', $url, ['confirm' => 'delete']);
        $this->assertSame('/admin/users', $response->getHeaderLine('Location'));
        $this->assertNull(User::query()->withoutCache()->find($this->target->id), 'hidden');
        $this->assertNotNull(User::query()->withoutCache()->onlyTrashed()->find($this->target->id), 'but still there');
        $this->assertNull(Page::query()->withoutCache()->find($page->id));
        $this->assertNotNull(Page::query()->withoutCache()->onlyTrashed()->find($page->id));
        $this->assertSame(1, SocialAccount::query()->count(), 'sign-ins stay');
        $this->assertSame('cancelled', Order::query()->first()->status, 'an unpaid order is cancelled, not erased');
        $this->assertSame([$kept->id], array_map('intval', Photo::query()->pluck('id')->all()));
        $this->assertSame(2, Photo::query()->withTrashed()->count());
        $this->assertSame([], $this->disk->deleted, 'no file is deleted');
        $this->assertNotNull(Page::query()->withoutCache()->find($mine->id), 'someone else\'s page is untouched');

        // it can be brought back, with the page and photo that went with it
        $removal = $this->app->make(\App\Services\AccountRemoval::class);
        $this->assertSame(['pages' => 1, 'photos' => 1], $removal->restore(User::query()->onlyTrashed()->find($this->target->id)));
        $this->assertNotNull(User::query()->withoutCache()->find($this->target->id));
        $this->assertNotNull(Page::query()->withoutCache()->find($page->id));
        $this->assertSame(2, Photo::query()->count());
    }

    public function testErasingNeedsAnAlreadyDeletedAccountAndRemovesEverythingForGood(): void
    {
        $page = $this->makePage($this->target);
        $this->makePhoto($page, 'https://cdn.test/a.jpg', 1);
        $removal = $this->app->make(\App\Services\AccountRemoval::class);
        try {
            $removal->erase($this->target);
            $this->fail('an account that is not deleted cannot be erased');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('delete it first', $e->getMessage());
        }
        $this->submit('DELETE', '/admin/users/' . $this->target->id, ['confirm' => 'delete']);
        $removal->erase(User::query()->onlyTrashed()->find($this->target->id));
        $this->assertNull(User::query()->withTrashed()->find($this->target->id));
        $this->assertSame(0, Page::query()->withTrashed()->where('user_id', $this->target->id)->count());
        $this->assertSame(['users/' . $this->target->id . '/photos/a.jpg'], $this->disk->deleted, 'only now are the files deleted');
    }

    public function testAnAccountWithPaidOrdersOrAnAdministratorIsNotDeleted(): void
    {
        $paid = new Order();
        $paid->forceFill(['reference' => 'LM-CCCC-DDDD', 'user_id' => $this->target->id, 'page_id' => null, 'plan' => 'sweet', 'period' => 'month', 'amount' => 20000, 'currency' => 'PHP', 'status' => 'paid', 'provider' => 'manual', 'paid_at' => gmdate('Y-m-d H:i:s')])->save();
        $this->submit('DELETE', '/admin/users/' . $this->target->id, ['confirm' => 'delete']);
        $this->assertNotNull($this->fresh($this->target));
        $this->assertSame(1, Order::query()->count());
        $this->assertStringContainsString('Deactivate it instead', (string) $this->get('/admin/users/' . $this->target->id . '/edit')->getBody());

        $this->submit('DELETE', '/admin/users/' . $this->admin->id, ['confirm' => 'delete']);
        $this->assertNotNull($this->fresh($this->admin));
    }

    public function testOnlyAdministratorsCanReachAnyOfIt(): void
    {
        $this->asVisitor();
        $user = $this->makeUser('plain@example.com');
        $this->signIn($user);
        $id = $this->target->id;
        foreach ([['GET', "/admin/users/{$id}/edit"], ['PUT', "/admin/users/{$id}"], ['POST', "/admin/users/{$id}/status"], ['DELETE', "/admin/users/{$id}"]] as [$method, $uri]) {
            $response = $method === 'GET' ? $this->get($uri) : $this->submit($method, $uri, ['status' => 'suspended', 'confirm' => 'delete', 'name' => 'x', 'email' => 'x@example.com']);
            $this->assertSame(404, $response->getStatusCode(), "$method $uri");
        }
        $this->assertSame('active', $this->fresh($this->target)->status);
        $this->assertNotNull($this->fresh($this->target));
    }

    public function testTheListFiltersByStatusAndShowsIt(): void
    {
        $this->setStatus($this->target, 'suspended', 'why');
        $html = (string) $this->get('/admin/users?status=suspended')->getBody();
        $this->assertStringContainsString('target@example.com', $html);
        $this->assertStringContainsString('pill-suspended', $html);
        $this->assertStringNotContainsString('<span class="small muted">boss@example.com</span>', $html);
        $this->assertStringContainsString('data-user-action="activate"', $html);
    }

    public function testAReturnPathCannotLeaveTheAdminArea(): void
    {
        foreach (['https://evil.test/', '//evil.test', 'dashboard'] as $return) {
            $response = $this->submit('POST', '/admin/users/' . $this->target->id . '/status', ['status' => 'deactivated', 'return' => $return]);
            $this->assertSame('/admin/users/' . $this->target->id . '/edit', $response->getHeaderLine('Location'), $return);
            $this->setStatus($this->target, 'active');
        }
        $ok = $this->submit('POST', '/admin/users/' . $this->target->id . '/status', ['status' => 'deactivated', 'return' => '/admin/users?status=active']);
        $this->assertSame('/admin/users?status=active', $ok->getHeaderLine('Location'));
    }
}
