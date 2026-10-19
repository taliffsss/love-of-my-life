<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Naluz\Tests\TestCase;

final class SocialLoginTest extends TestCase
{
    private const CONFIG = [
        'google' => ['client_id' => 'g-id', 'client_secret' => 'g-secret'],
        'facebook' => ['client_id' => 'f-id', 'client_secret' => 'f-secret', 'graph_version' => 'v26.0'],
    ];

    /** @param list<GuzzleResponse|\Throwable> $answers what the provider answers, in order */
    private function providers(array $answers = [], array $config = self::CONFIG): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($answers)), 'http_errors' => false]);
        $this->app->instance(SocialAuth::class, new SocialAuth($config, 'https://love.test', $client));
    }

    private function answer(array $data, int $status = 200): GuzzleResponse
    {
        return new GuzzleResponse($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    /** @return GuzzleResponse[] the two answers of a Google sign-in */
    private function google(array $profile): array
    {
        return [$this->answer(['access_token' => 'tok']), $this->answer($profile)];
    }

    private function facebook(array $profile): array
    {
        return [$this->answer(['access_token' => 'tok']), $this->answer($profile)];
    }

    /** Starts the flow and returns the state the provider would send back. */
    private function begin(string $provider): string
    {
        $response = $this->get('/auth/' . $provider . '/redirect');
        $this->assertSame(302, $response->getStatusCode());
        parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        return (string) $query['state'];
    }

    private function returnFrom(string $provider, string $state, string $code = 'abc')
    {
        return $this->get('/auth/' . $provider . '/callback?' . http_build_query(['code' => $code, 'state' => $state]));
    }

    private function assertSignedIn(bool $in): void
    {
        $this->assertSame($in ? 200 : 302, $this->get('/dashboard')->getStatusCode());
    }

    public function testNothingShowsWhenNoProviderIsConfigured(): void
    {
        $this->providers([], ['google' => ['client_id' => '', 'client_secret' => ''], 'facebook' => ['client_id' => 'x', 'client_secret' => '']]);
        $this->assertStringNotContainsString('Continue with', (string) $this->get('/login')->getBody());
        $this->assertSame(404, $this->get('/auth/google/redirect')->getStatusCode());
        $this->assertSame(404, $this->get('/auth/facebook/callback?code=a&state=b')->getStatusCode());
    }

    public function testBothButtonsAppearOnSignInAndSignUp(): void
    {
        $this->providers();
        foreach (['/login', '/register'] as $uri) {
            $html = (string) $this->get($uri)->getBody();
            $this->assertStringContainsString('href="/auth/google/redirect"', $html);
            $this->assertStringContainsString('href="/auth/facebook/redirect"', $html);
        }
    }

    public function testTheRedirectGoesToTheProviderWithStateAndPkce(): void
    {
        $this->providers();
        $google = $this->get('/auth/google/redirect')->getHeaderLine('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $google);
        parse_str((string) parse_url($google, PHP_URL_QUERY), $q);
        $this->assertSame('g-id', $q['client_id']);
        $this->assertSame('https://love.test/auth/google/callback', $q['redirect_uri']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertNotSame('', $q['state']);

        $facebook = $this->get('/auth/facebook/redirect')->getHeaderLine('Location');
        $this->assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $facebook);
        parse_str((string) parse_url($facebook, PHP_URL_QUERY), $q);
        $this->assertSame('email,public_profile', $q['scope']);
    }

    public function testANewGoogleUserGetsAnAccountAndIsSignedIn(): void
    {
        $this->providers($this->google(['sub' => '1001', 'name' => 'Gina Reyes', 'email' => 'Gina@Example.com', 'email_verified' => true]));
        $state = $this->begin('google');
        $response = $this->returnFrom('google', $state);

        $this->assertSame('/dashboard', $response->getHeaderLine('Location'));
        $user = User::query()->where('email', 'gina@example.com')->first();
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('google', $user->login_type);
        $this->assertFalse($user->is_admin);
        $this->assertSame('Gina Reyes', $user->name);
        $this->assertSame(1, SocialAccount::query()->where('user_id', $user->id)->count());
        $this->assertSignedIn(true);
    }

    public function testAReturningGoogleUserSignsInToTheSameAccount(): void
    {
        $profile = ['sub' => '1001', 'name' => 'Gina', 'email' => 'gina@example.com', 'email_verified' => true];
        $this->providers([...$this->google($profile), ...$this->google(['email' => 'changed@example.com'] + $profile)]);
        $this->returnFrom('google', $this->begin('google'));
        $this->submit('POST', '/logout');
        $this->assertSignedIn(false);

        $this->returnFrom('google', $this->begin('google'));
        $this->assertSignedIn(true);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, SocialAccount::query()->count());
    }

    public function testAnExistingPasswordAccountIsNeverLinkedByEmail(): void
    {
        $owner = $this->makeUser('gina@example.com');
        $this->providers($this->google(['sub' => '1001', 'name' => 'Gina', 'email' => 'gina@example.com', 'email_verified' => true]));
        $response = $this->returnFrom('google', $this->begin('google'));

        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertSame(0, SocialAccount::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame('manual', User::query()->find($owner->id)->login_type);
        $this->assertSignedIn(false);
    }

    public function testGoogleMustVouchForTheAddress(): void
    {
        $this->providers($this->google(['sub' => '1001', 'name' => 'Gina', 'email' => 'gina@example.com', 'email_verified' => false]));
        $this->returnFrom('google', $this->begin('google'));
        $this->assertSame(0, User::query()->count());
        $this->assertSignedIn(false);
    }

    public function testFacebookMakesAnAccountFromItsAddressAndRefusesWithoutOne(): void
    {
        $this->providers($this->facebook(['id' => '77', 'name' => 'Fe Cruz', 'email' => 'fe@example.com']));
        $this->returnFrom('facebook', $this->begin('facebook'));
        $user = User::query()->where('email', 'fe@example.com')->first();
        $this->assertSame('facebook', $user->login_type);
        $this->assertSignedIn(true);
        $this->submit('POST', '/logout');

        $this->providers($this->facebook(['id' => '78', 'name' => 'No Mail']));
        $this->returnFrom('facebook', $this->begin('facebook'));
        $this->assertSame(1, User::query()->count());
        $this->assertSignedIn(false);
    }

    public function testAnAdministratorNeverSignsInThroughAProvider(): void
    {
        $admin = new User();
        $admin->forceFill(['name' => 'Admin', 'email' => 'boss@example.com', 'password' => 'x-long-password', 'is_admin' => true, 'login_type' => 'google'])->save();
        $link = new SocialAccount();
        $link->forceFill(['user_id' => $admin->id, 'provider' => 'google', 'provider_uid' => '9'])->save();

        $this->providers($this->google(['sub' => '9', 'name' => 'Admin', 'email' => 'boss@example.com', 'email_verified' => true]));
        $response = $this->returnFrom('google', $this->begin('google'));
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $this->assertSignedIn(false);
    }

    public function testTheStateMustMatchAndWorksOnce(): void
    {
        $this->providers([...$this->google(['sub' => '1', 'name' => 'A', 'email' => 'a@example.com', 'email_verified' => true])]);
        $state = $this->begin('google');

        $this->assertSame('/login', $this->returnFrom('google', 'forged')->getHeaderLine('Location'));
        // a wrong state used up the saved one too: the real state no longer works
        $this->assertSame('/login', $this->returnFrom('google', $state)->getHeaderLine('Location'));
        $this->assertSame(0, User::query()->count());

        $this->assertSame('/login', $this->get('/auth/google/callback?code=abc')->getHeaderLine('Location'));
    }

    public function testCancellingAtTheProviderReturnsToSignIn(): void
    {
        $this->providers();
        $this->begin('google');
        $response = $this->get('/auth/google/callback?error=access_denied');
        $this->assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testAProviderOutageIsReportedWithoutLeakingAnything(): void
    {
        $this->providers([new ConnectException('cannot connect to https://x/?client_secret=g-secret', new PsrRequest('POST', 'https://x'))]);
        $response = $this->returnFrom('google', $this->begin('google'));
        $this->assertSame('/login', $response->getHeaderLine('Location'));
        $html = (string) $this->get('/login')->getBody();
        $this->assertStringNotContainsString('g-secret', $html);
        $this->assertSame(0, User::query()->count());
    }

    public function testASocialAccountCannotUseThePasswordForm(): void
    {
        $this->providers($this->google(['sub' => '1', 'name' => 'A', 'email' => 'a@example.com', 'email_verified' => true]));
        $this->returnFrom('google', $this->begin('google'));
        $this->submit('POST', '/logout');
        foreach (['', 'password', 'a@example.com'] as $guess) {
            $response = $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'a@example.com', 'password' => $guess]);
            $this->assertNotSame('/dashboard', $response->getHeaderLine('Location'));
        }
    }
}
