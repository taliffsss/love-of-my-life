<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\User;
use Naluz\Tests\TestCase;

final class AuthTest extends TestCase
{
    public function testRegisteringCreatesTheAccountAndSignsIn(): void
    {
        $response = $this->form('POST', '/register', [
            '_token' => $this->csrf('/register'),
            'name' => 'Mark',
            'email' => '  Mark@Example.COM ',
            'password' => 'long enough',
            'password_confirmation' => 'long enough',
        ]);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/dashboard', $response->getHeaderLine('Location'));
        $user = User::query()->where('email', 'mark@example.com')->first();
        $this->assertInstanceOf(User::class, $user, 'the e-mail is stored trimmed and lower-cased');
        $this->assertNotSame('long enough', $user->getRawAttributes()['password'], 'the password is hashed');
        $this->assertStringStartsWith('$', (string) $user->getRawAttributes()['password']);
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode(), 'the new account is signed in');
    }

    public function testThePlanChosenBeforeSigningUpIsSavedWithTheNewAccount(): void
    {
        $this->assertStringContainsString('You chose <strong>Sweet</strong>', (string) $this->get('/register?plan=sweet')->getBody());
        $this->form('POST', '/register', [
            '_token' => $this->csrf('/register'),
            'name' => 'Mark', 'email' => 'mark@example.com', 'password' => 'long enough', 'password_confirmation' => 'long enough',
        ]);
        $this->assertSame('sweet', User::query()->where('email', 'mark@example.com')->first()->intended_plan);

        // the dashboard shows only the 5 templates of Sweet for each occasion, with a way to see them all
        $html = (string) $this->get('/dashboard')->getBody();
        $this->assertStringContainsString('Showing the 5 templates per occasion that come with Sweet', $html);
        $this->assertSame(5 * 5, substr_count($html, 'name="template"'), '5 occasions, 5 templates each');
        $this->assertSame(10 * 5, substr_count((string) $this->get('/dashboard?templates=all')->getBody(), 'name="template"'));
    }

    public function testAnUnknownOrFreePlanIsNotKept(): void
    {
        foreach (['free', 'nonsense', '<script>'] as $plan) {
            $this->get('/register?plan=' . urlencode($plan));
        }
        $this->form('POST', '/register', [
            '_token' => $this->csrf('/register'),
            'name' => 'Mark', 'email' => 'mark@example.com', 'password' => 'long enough', 'password_confirmation' => 'long enough',
        ]);
        $this->assertNull(User::query()->where('email', 'mark@example.com')->first()->intended_plan);
        $this->assertSame(50, substr_count((string) $this->get('/dashboard')->getBody(), 'name="template"'), 'no plan chosen: every template, as before');
    }

    public function testSigningInKeepsThePlanPickedOnThePricingSection(): void
    {
        $user = $this->makeUser('mark@example.com', 'long enough');
        $this->get('/login?plan=sweetheart');
        $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'mark@example.com', 'password' => 'long enough']);
        $this->assertSame('sweetheart', User::query()->where('id', $user->id)->first()->intended_plan);
        $this->assertSame(50, substr_count((string) $this->get('/dashboard')->getBody(), 'name="template"'), 'Sweetheart has every template: nothing to narrow');
    }

    public function testRegistrationValidatesTheFields(): void
    {
        $this->makeUser('taken@example.com');
        // a full URL: "back" is the Referer only when it points at the same host
        $response = $this->form('POST', 'https://love.test/register', [
            '_token' => $this->csrf('/register'),
            'name' => '',
            'email' => 'TAKEN@example.com',
            'password' => 'short',
            'password_confirmation' => 'other',
        ], ['Referer' => 'https://love.test/register']);

        $this->assertSame(303, $response->getStatusCode());
        $this->assertSame('/register', $response->getHeaderLine('Location'));
        $html = (string) $this->get('/register')->getBody();
        $this->assertStringContainsString('There is already an account with this e-mail', $html);
        $this->assertStringContainsString('Tell us your name.', $html);
        $this->assertStringContainsString('value="taken@example.com"', $html, 'the e-mail is kept');
        $this->assertSame(1, User::query()->count());
    }

    public function testRegistrationNeedsTheCsrfToken(): void
    {
        $response = $this->form('POST', '/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'long enough', 'password_confirmation' => 'long enough']);
        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame(0, User::query()->count());
    }

    public function testSignInAndSignOut(): void
    {
        $user = $this->makeUser();
        $this->signIn($user);
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode());

        $response = $this->form('POST', '/logout', ['_token' => $this->csrf('/dashboard')]);
        $this->assertSame('/', $response->getHeaderLine('Location'));
        $this->assertSame('/login', $this->get('/dashboard')->getHeaderLine('Location'), 'signed out');
    }

    public function testAWrongPasswordAndAnUnknownEmailGetTheSameAnswer(): void
    {
        $this->makeUser('owner@example.com');
        $this->form('POST', 'https://love.test/login', ['_token' => $this->csrf('/login'), 'email' => 'owner@example.com', 'password' => 'wrong'], ['Referer' => 'https://love.test/login']);
        $wrongPassword = (string) $this->get('/login')->getBody();
        $this->form('POST', 'https://love.test/login', ['_token' => $this->csrf('/login'), 'email' => 'nobody@example.com', 'password' => 'wrong'], ['Referer' => 'https://love.test/login']);
        $unknown = (string) $this->get('/login')->getBody();

        $this->assertStringContainsString('That e-mail and password do not match an account.', $wrongPassword);
        $this->assertStringContainsString('That e-mail and password do not match an account.', $unknown);
        $this->assertSame('/login', $this->get('/dashboard')->getHeaderLine('Location'));
    }

    public function testTheDashboardNeedsAnAccount(): void
    {
        $this->assertSame('/login', $this->get('/dashboard')->getHeaderLine('Location'));
        $this->assertSame(401, $this->get('/dashboard', ['Accept' => 'application/json'])->getStatusCode());
    }

    public function testSignedInVisitorsSkipTheSignInPages(): void
    {
        $this->signIn($this->makeUser());
        $this->assertSame('/dashboard', $this->get('/login')->getHeaderLine('Location'));
        $this->assertSame('/dashboard', $this->get('/register')->getHeaderLine('Location'));
    }

    public function testSignInIsRateLimited(): void
    {
        $token = $this->csrf('/login');
        $codes = [];
        for ($i = 0; $i < 11; $i++) {
            $codes[] = $this->form('POST', '/login', ['_token' => $token, 'email' => 'a@example.com', 'password' => 'x'])->getStatusCode();
        }
        $this->assertSame(429, end($codes));
        $this->assertSame(429, $this->form('POST', '/login/', ['_token' => $token, 'email' => 'a@example.com', 'password' => 'x'])->getStatusCode(), 'a trailing slash is the same route');
        $this->assertSame(429, $this->form('POST', '/%6Cogin', ['_token' => $token, 'email' => 'a@example.com', 'password' => 'x'])->getStatusCode(), 'so is an encoded letter');
    }
}
