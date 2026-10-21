<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\User;
use App\Services\OneTimeCode;
use Naluz\Tests\TestCase;

final class EmailConfirmationTest extends TestCase
{
    private function register(string $email = 'mark@example.com'): void
    {
        $this->form('POST', '/register', [
            '_token' => $this->csrf('/register'), 'name' => 'Mark', 'email' => $email, 'password' => 'long enough', 'password_confirmation' => 'long enough',
        ]);
    }

    private function enter(string $code): \Psr\Http\Message\ResponseInterface
    {
        return $this->form('POST', '/email/verify', ['_token' => $this->csrf('/email/verify'), 'code' => $code]);
    }

    public function testAManualSignUpIsMailedACodeAndHeldBackFromTheDashboardUntilItIsEntered(): void
    {
        $mail = $this->mailbox();
        $this->register();

        $user = User::query()->where('email', 'mark@example.com')->first();
        $this->assertNull($user->email_verified_at);
        $this->assertCount(1, $mail->sent);
        $this->assertSame('mark@example.com', $mail->sent[0]->getTo()[0]->email);
        $this->assertMatchesRegularExpression('/^\d{6} is your .* confirmation code$/', $mail->sent[0]->getSubject());
        $this->assertNotSame('', (string) $user->getRawAttributes()['verify_code_hash']);
        $this->assertStringNotContainsString($this->lastCode($mail), (string) $user->getRawAttributes()['verify_code_hash'], 'only a keyed hash is stored');

        $held = $this->get('/dashboard');
        $this->assertSame(303, $held->getStatusCode());
        $this->assertSame('/email/verify', $held->getHeaderLine('Location'));
        $this->assertStringContainsString('mark@example.com', (string) $this->get('/email/verify')->getBody());

        $this->assertSame('/dashboard', $this->enter($this->lastCode($mail))->getHeaderLine('Location'));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame('', (string) $user->fresh()->getRawAttributes()['verify_code_hash'], 'the code works once');
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode());
    }

    public function testAWrongCodeIsRefusedAndFiveWrongOnesVoidTheCode(): void
    {
        $mail = $this->mailbox();
        $this->register();
        $real = $this->lastCode($mail);
        $wrong = $real === '000000' ? '111111' : '000000';

        for ($i = 1; $i <= 4; $i++) {
            $this->enter($wrong);
            $this->assertStringContainsString('That code is not right', (string) $this->get('/email/verify')->getBody());
        }
        $this->enter($wrong); // the fifth
        $this->assertStringContainsString('Too many wrong tries', (string) $this->get('/email/verify')->getBody());
        $this->enter($real); // even the right one no longer works
        $this->assertNull(User::query()->where('email', 'mark@example.com')->first()->email_verified_at);
        $this->assertSame('/email/verify', $this->get('/dashboard')->getHeaderLine('Location'));
    }

    public function testAnExpiredCodeIsRefused(): void
    {
        $mail = $this->mailbox();
        $this->register();
        $user = User::query()->where('email', 'mark@example.com')->first();
        $user->forceFill(['verify_expires_at' => new \DateTimeImmutable('-1 minute')])->save();

        $this->enter($this->lastCode($mail));
        $this->assertStringContainsString('expired', (string) $this->get('/email/verify')->getBody());
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function testANewCodeCannotBeAskedForForAMinuteAndReplacesTheOldOne(): void
    {
        $mail = $this->mailbox();
        $this->register();
        $first = $this->lastCode($mail);

        $this->form('POST', '/email/verification-notification', ['_token' => $this->csrf('/email/verify')]);
        $this->assertCount(1, $mail->sent, 'too soon: nothing new is sent');

        User::query()->where('email', 'mark@example.com')->first()->forceFill(['verify_sent_at' => new \DateTimeImmutable('-2 minutes')])->save();
        $this->form('POST', '/email/verification-notification', ['_token' => $this->csrf('/email/verify')]);
        $this->assertCount(2, $mail->sent);
        $second = $this->lastCode($mail);
        if ($first !== $second) {
            $this->enter($first);
            $this->assertNull(User::query()->where('email', 'mark@example.com')->first()->email_verified_at, 'the old code no longer works');
        }
        $this->enter($second);
        $this->assertNotNull(User::query()->where('email', 'mark@example.com')->first()->email_verified_at);
    }

    public function testWithoutAMailerNobodyIsHeldBack(): void
    {
        $this->register(); // the log mailer, "auto": confirmation is off, as nobody could receive a code
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode());
    }

    public function testAnAccountThatExistedBeforeIsNotLockedOut(): void
    {
        $this->mailbox();
        $owner = $this->makeUser(); // makeUser() confirms the address, like the migration does for old accounts
        $this->signIn($owner);
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode());
        $this->assertSame('/dashboard', $this->get('/email/verify')->getHeaderLine('Location'), 'nothing to enter');
    }

    public function testACodeIsOnlyGoodForItsOwnAccountAndPurpose(): void
    {
        $a = OneTimeCode::hash('123456', 1, 'verify');
        $this->assertTrue(OneTimeCode::matches('123 456', $a, 1, 'verify') === false, 'spaces are cleaned by clean(), not matches()');
        $this->assertTrue(OneTimeCode::matches(OneTimeCode::clean('123 456'), $a, 1, 'verify'));
        $this->assertFalse(OneTimeCode::matches('123456', $a, 2, 'verify'));
        $this->assertFalse(OneTimeCode::matches('123456', $a, 1, 'reset'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', OneTimeCode::make());
    }

    public function testChangingTheEmailWaitsForACodeSentToTheNewAddress(): void
    {
        $mail = $this->mailbox();
        $owner = $this->makeUser();
        $this->signIn($owner);

        $this->submit('PUT', '/account/profile', ['name' => 'Owner', 'email' => 'new@example.com']);
        $this->assertSame('owner@example.com', $owner->fresh()->email, 'not changed yet');
        $this->assertSame('new@example.com', $owner->fresh()->pending_email);
        $this->assertSame('new@example.com', $mail->sent[0]->getTo()[0]->email);
        $this->assertStringContainsString('new@example.com', (string) $this->get('/email/verify')->getBody());

        $this->enter($this->lastCode($mail));
        $this->assertSame('new@example.com', $owner->fresh()->email);
        $this->assertNull($owner->fresh()->pending_email);
    }

    public function testACodeThatIsNotSixDigitsIsRefusedWithoutCountingAsAWrongTry(): void
    {
        $mail = $this->mailbox();
        $this->register();
        foreach (['', '12345', '1234567', 'abcdef'] as $typed) {
            $this->enter($typed);
            $this->assertStringContainsString('Enter the 6-digit code', (string) $this->get('/email/verify')->getBody(), "[{$typed}]");
        }
        $this->assertSame(0, User::query()->where('email', 'mark@example.com')->first()->verify_attempts, 'no try was used');
        $this->enter($this->lastCode($mail));
        $this->assertNotNull(User::query()->where('email', 'mark@example.com')->first()->email_verified_at);
    }
}
