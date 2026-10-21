<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Naluz\Mail\Mailer;

/**
 * E-mail confirmation with a one-time code (App\Services\OneTimeCode) mailed to the address: a new sign-up's, or the new address asked
 * for in the profile (`pending_email`; it becomes the sign-in e-mail once the code is entered).
 *
 * It is on when `saas.email_verification` is true, or "auto" (the default) and a real mailer is set up (MAIL_MAILER=smtp / EMAIL_HOST):
 * with only the log mailer nobody could receive the code, so a manual sign-up is not held back by it. A Google or Facebook account is
 * confirmed by the provider and never needs this.
 */
final class EmailVerification
{
    private const PURPOSE = 'verify';

    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function enabled(): bool
    {
        $setting = config('saas.email_verification', 'auto');
        if ($setting === 'auto') {
            return config('mail.default') === 'smtp';
        }
        return filter_var($setting, FILTER_VALIDATE_BOOLEAN);
    }

    /** Whether this account has to confirm its address before using the dashboard. */
    public function required(User $user): bool
    {
        return $this->enabled() && !$user->hasVerifiedEmail();
    }

    /** Whether there is a code to enter: a sign-up that is not confirmed, or a new address waiting for its code. */
    public function waiting(User $user): bool
    {
        return $this->required($user) || ($user->pending_email !== null && $user->pending_email !== '');
    }

    public function canResend(User $user, ?\DateTimeImmutable $now = null): bool
    {
        return OneTimeCode::canResend($user->verify_sent_at, $now);
    }

    /** Mails a new code to the pending address, or to the account's own address. Returns false (and logs why) when it could not be sent. */
    public function send(User $user, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();
        $code = OneTimeCode::make();
        $user->forceFill([
            'verify_code_hash' => OneTimeCode::hash($code, $user->id, self::PURPOSE),
            'verify_expires_at' => $now->modify('+' . OneTimeCode::TTL . ' seconds'),
            'verify_sent_at' => $now,
            'verify_attempts' => 0,
        ])->save();

        try {
            $message = $this->mailer->message()->to((string) ($user->pending_email ?: $user->email), $user->name)
                ->subject($code . ' is your ' . config('saas.brand') . ' confirmation code');
            $this->mailer->view($message, 'emails.code', [
                'brand' => (string) config('saas.brand'), 'name' => $user->name, 'code' => $code, 'minutes' => intdiv(OneTimeCode::TTL, 60),
                'headline' => $user->pending_email ? 'Confirm your new e-mail' : 'Confirm your e-mail',
                'intro' => $user->pending_email
                    ? 'You asked to use this address for your account. Enter this code and it becomes the e-mail you sign in with.'
                    : 'Welcome! Enter this code to confirm your e-mail, and you can start building a page for someone you love.',
            ]);
            $this->mailer->send($message);
            logger()->info('[mail] confirmation code sent to user {user}', ['user' => $user->id]);
            return true;
        } catch (\Throwable $e) {
            logger()->error('[mail] could not send the confirmation code to user {user}: {error}', ['user' => $user->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Checks the code the person typed.
     *
     * @return 'ok'|'invalid'|'expired'|'locked'|'taken' `locked`: too many wrong tries, ask for a new code; `taken`: the new address was taken by another account meanwhile
     */
    public function confirm(User $user, string $typed, ?\DateTimeImmutable $now = null): string
    {
        $now ??= new \DateTimeImmutable();
        $user = User::query()->withoutCache()->useWritePdo()->where('id', $user->id)->first() ?? $user;
        if ((string) $user->verify_code_hash === '') {
            return 'expired';
        }
        if ($user->verify_expires_at === null || $user->verify_expires_at < $now) {
            return 'expired';
        }
        if ($user->verify_attempts >= OneTimeCode::MAX_ATTEMPTS) {
            return 'locked';
        }
        if (!OneTimeCode::matches(OneTimeCode::clean($typed), $user->verify_code_hash, $user->id, self::PURPOSE)) {
            $attempts = $user->verify_attempts + 1;
            $user->forceFill($attempts >= OneTimeCode::MAX_ATTEMPTS
                ? ['verify_code_hash' => null, 'verify_expires_at' => null, 'verify_attempts' => 0]
                : ['verify_attempts' => $attempts])->save();
            return $attempts >= OneTimeCode::MAX_ATTEMPTS ? 'locked' : 'invalid';
        }

        $changes = ['email_verified_at' => $now, 'verify_code_hash' => null, 'verify_expires_at' => null, 'verify_attempts' => 0, 'pending_email' => null];
        if ($user->pending_email !== null && $user->pending_email !== '') {
            $taken = User::query()->withoutCache()->useWritePdo()->withTrashed()->where('email', $user->pending_email)->where('id', '!=', $user->id)->exists();
            if ($taken) {
                $user->forceFill(['pending_email' => null, 'verify_code_hash' => null, 'verify_expires_at' => null, 'verify_attempts' => 0])->save();
                return 'taken';
            }
            $changes['email'] = $user->pending_email;
        }
        $user->forceFill($changes)->save();
        return 'ok';
    }
}
