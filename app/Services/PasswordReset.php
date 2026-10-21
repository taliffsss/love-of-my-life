<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Naluz\Mail\Mailer;

/**
 * "Forgot password": a one-time code (App\Services\OneTimeCode) is mailed to the account's address and, with the new password, resets it.
 * Asking never says whether an account exists (the screen is the same either way). A code that arrives proves the address, so a reset
 * also confirms it. An account that is deactivated, suspended or deleted gets no code.
 */
final class PasswordReset
{
    private const PURPOSE = 'reset';

    public function __construct(private readonly Mailer $mailer)
    {
    }

    /** Mails a code when the address belongs to an active account (and one was not sent a moment ago); does nothing otherwise. */
    public function request(string $email, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $user = User::query()->withoutCache()->useWritePdo()->where('email', User::normaliseEmail($email))->first();
        if (!$user instanceof User || !$user->isActive() || !OneTimeCode::canResend($user->reset_sent_at, $now)) {
            return;
        }
        $code = OneTimeCode::make();
        $user->forceFill([
            'reset_code_hash' => OneTimeCode::hash($code, $user->id, self::PURPOSE),
            'reset_expires_at' => $now->modify('+' . OneTimeCode::TTL . ' seconds'),
            'reset_sent_at' => $now,
            'reset_attempts' => 0,
        ])->save();
        try {
            $message = $this->mailer->message()->to($user->email, $user->name)->subject($code . ' is your ' . config('saas.brand') . ' password reset code');
            $this->mailer->view($message, 'emails.code', [
                'brand' => (string) config('saas.brand'), 'name' => $user->name, 'code' => $code, 'minutes' => intdiv(OneTimeCode::TTL, 60),
                'headline' => 'Reset your password',
                'intro' => 'Someone asked to reset the password of your account. Enter this code with your new password. If it was not you, ignore this e-mail: nothing changes.',
            ]);
            $this->mailer->send($message);
            logger()->info('[mail] password reset code sent to user {user}', ['user' => $user->id]);
        } catch (\Throwable $e) {
            logger()->error('[mail] could not send the password reset code to user {user}: {error}', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /** @return 'ok'|'invalid'|'expired'|'locked' the same words as EmailVerification::confirm() */
    public function reset(string $email, string $typed, string $password, ?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable();
        $user = User::query()->withoutCache()->useWritePdo()->where('email', User::normaliseEmail($email))->first();
        if (!$user instanceof User || !$user->isActive() || (string) $user->reset_code_hash === '' || $user->reset_expires_at === null || $user->reset_expires_at < $now) {
            return 'expired'; // also the answer for an address with no account, so the form reveals nothing
        }
        if ($user->reset_attempts >= OneTimeCode::MAX_ATTEMPTS) {
            return 'locked';
        }
        if (!OneTimeCode::matches(OneTimeCode::clean($typed), $user->reset_code_hash, $user->id, self::PURPOSE)) {
            $attempts = $user->reset_attempts + 1;
            $user->forceFill($attempts >= OneTimeCode::MAX_ATTEMPTS
                ? ['reset_code_hash' => null, 'reset_expires_at' => null, 'reset_attempts' => 0]
                : ['reset_attempts' => $attempts])->save();
            return $attempts >= OneTimeCode::MAX_ATTEMPTS ? 'locked' : 'invalid';
        }
        $user->forceFill([
            'password' => $password, // hashed by the model cast
            'reset_code_hash' => null, 'reset_expires_at' => null, 'reset_attempts' => 0,
            'email_verified_at' => $user->email_verified_at ?? $now,
        ])->save();
        return 'ok';
    }
}
