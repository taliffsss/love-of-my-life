<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\User;
use Naluz\Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    private function ask(string $email): \Psr\Http\Message\ResponseInterface
    {
        return $this->form('POST', '/forgot-password', ['_token' => $this->csrf('/forgot-password'), 'email' => $email]);
    }

    private function reset(string $email, string $code, string $password = 'a brand new one'): \Psr\Http\Message\ResponseInterface
    {
        return $this->form('POST', '/reset-password', ['_token' => $this->csrf('/reset-password'), 'email' => $email, 'code' => $code, 'password' => $password, 'password_confirmation' => $password]);
    }

    public function testTheLoginPageLinksToIt(): void
    {
        $this->assertStringContainsString('href="/forgot-password"', (string) $this->get('/login')->getBody());
        $this->assertSame(200, $this->get('/forgot-password')->getStatusCode());
        $this->assertSame(200, $this->get('/reset-password')->getStatusCode());
    }

    public function testACodeByEmailResetsThePasswordAndOnlyOnce(): void
    {
        $mail = $this->mailbox(false);
        $owner = $this->makeUser();

        $response = $this->ask('  OWNER@example.com ');
        $this->assertSame('/reset-password', $response->getHeaderLine('Location'));
        $this->assertCount(1, $mail->sent);
        $this->assertSame('owner@example.com', $mail->sent[0]->getTo()[0]->email);
        $this->assertMatchesRegularExpression('/^\d{6} is your .* password reset code$/', $mail->sent[0]->getSubject());
        $code = $this->lastCode($mail);

        $this->assertSame('/login', $this->reset('owner@example.com', $code)->getHeaderLine('Location'));
        $this->signIn($owner, 'a brand new one');
        $this->assertSame(200, $this->get('/dashboard')->getStatusCode());

        $this->form('POST', '/logout', ['_token' => $this->csrf('/')]);
        $this->reset('owner@example.com', $code, 'yet another one');
        $this->assertStringContainsString('expired', (string) $this->get('/reset-password')->getBody(), 'the code works once');
    }

    public function testItNeverSaysWhetherAnAccountExists(): void
    {
        $mail = $this->mailbox(false);
        $this->makeUser();
        $known = $this->ask('owner@example.com');
        $unknown = $this->ask('nobody@example.com');
        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($known->getHeaderLine('Location'), $unknown->getHeaderLine('Location'));
        $this->assertCount(1, $mail->sent, 'only the real account got an e-mail');
        $this->reset('nobody@example.com', '123456');
        $this->assertStringContainsString('expired or was not sent', (string) $this->get('/reset-password')->getBody(), 'the same words as for an expired code');
    }

    public function testWrongCodesAreCountedAndFiveVoidTheCode(): void
    {
        $mail = $this->mailbox(false);
        $this->makeUser();
        $this->ask('owner@example.com');
        $real = $this->lastCode($mail);
        $wrong = $real === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->reset('owner@example.com', $wrong);
        }
        $this->assertStringContainsString('Too many wrong tries', (string) $this->get('/reset-password')->getBody());
        $this->reset('owner@example.com', $real);
        $this->form('POST', '/login', ['_token' => $this->csrf('/login'), 'email' => 'owner@example.com', 'password' => 'a brand new one']);
        $this->assertSame('/login', $this->get('/dashboard')->getHeaderLine('Location'), 'the voided code changed nothing: the new password does not sign in');
    }

    public function testAnInactiveAccountIsSentNothing(): void
    {
        $mail = $this->mailbox(false);
        $owner = $this->makeUser();
        $owner->forceFill(['status' => User::SUSPENDED])->save();
        $this->ask('owner@example.com');
        $this->assertCount(0, $mail->sent);
    }

    public function testAskingAgainWithinAMinuteSendsNothingNew(): void
    {
        $mail = $this->mailbox(false);
        $this->makeUser();
        $this->ask('owner@example.com');
        $this->ask('owner@example.com');
        $this->assertCount(1, $mail->sent);
    }

    public function testTheNewPasswordIsChecked(): void
    {
        $mail = $this->mailbox(false);
        $owner = $this->makeUser();
        $this->ask('owner@example.com');
        $code = $this->lastCode($mail);
        $this->form('POST', '/reset-password', ['_token' => $this->csrf('/reset-password'), 'email' => 'owner@example.com', 'code' => $code, 'password' => 'short', 'password_confirmation' => 'short']);
        $this->assertStringContainsString('at least 8 characters', (string) $this->get('/reset-password')->getBody());
        $this->form('POST', '/reset-password', ['_token' => $this->csrf('/reset-password'), 'email' => 'owner@example.com', 'code' => $code, 'password' => 'long enough pw', 'password_confirmation' => 'different pw!']);
        $this->assertStringContainsString('do not match', (string) $this->get('/reset-password')->getBody());
        $this->assertNotNull($owner->fresh()->getRawAttributes()['reset_code_hash'], 'the code is still good after a typo');
    }

    public function testTheCodeMustBeSixDigitsAndDoesNotCountAsATryOtherwise(): void
    {
        $mail = $this->mailbox(false);
        $owner = $this->makeUser();
        $this->ask('owner@example.com');
        foreach (['', '12345', 'abcdef'] as $typed) {
            $this->reset('owner@example.com', $typed);
            $this->assertStringContainsString('Enter the 6-digit code', (string) $this->get('/reset-password')->getBody(), "[{$typed}]");
        }
        $this->assertSame(0, $owner->fresh()->reset_attempts);
        $this->assertSame('/login', $this->reset('owner@example.com', $this->lastCode($mail))->getHeaderLine('Location'));
    }

    public function testTheEmailMustLookLikeOne(): void
    {
        $this->mailbox(false);
        $this->ask('not-an-email');
        $this->assertStringContainsString('Enter the e-mail address of your account', (string) $this->get('/forgot-password')->getBody());
    }
}
