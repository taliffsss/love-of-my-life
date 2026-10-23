<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Makes a normal (not administrator) account for testing a server from QA_EMAIL and QA_PASSWORD (12+ characters), at container
 * start (bin/ensure-qa.php), the way AdminBootstrap makes the administrator. It only ever creates: an account that already has that
 * e-mail is left exactly as it is, and an existing account is never promoted or reset. The e-mail is marked confirmed, so no code is asked.
 */
final class QaBootstrap
{
    /** @return 'created'|'exists'|'skipped' */
    public function ensure(string $email, string $password): string
    {
        $email = User::normaliseEmail($email);
        if ($email === '' || $password === '') {
            return 'skipped';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($password) < 12) {
            throw new \RuntimeException('QA_EMAIL must be an e-mail address and QA_PASSWORD at least 12 characters.');
        }
        if (User::query()->withoutCache()->useWritePdo()->withTrashed()->where('email', $email)->exists()) {
            return 'exists';
        }
        $user = new User();
        $user->forceFill([
            'name' => 'QA Tester', 'email' => $email, 'password' => $password, 'is_admin' => false, 'email_verified_at' => new \DateTimeImmutable(),
        ])->save();
        return 'created';
    }
}
