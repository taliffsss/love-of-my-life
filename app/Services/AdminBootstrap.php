<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Makes the first administrator on a server from ADMIN_EMAIL and ADMIN_PASSWORD (12+ characters), at container start (bin/ensure-admin.php).
 * It only ever creates: an account that already has that e-mail is left exactly as it is (a password changed in the profile is not
 * reset by the next deploy, and a normal account is not promoted), unlike `php naluz db:seed`, which applies ADMIN_PASSWORD again.
 */
final class AdminBootstrap
{
    /** @return 'created'|'exists'|'skipped' */
    public function ensure(string $email, string $password): string
    {
        $email = User::normaliseEmail($email);
        if ($email === '' || $password === '') {
            return 'skipped';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($password) < 12) {
            throw new \RuntimeException('ADMIN_EMAIL must be an e-mail address and ADMIN_PASSWORD at least 12 characters.');
        }
        if (User::query()->withoutCache()->useWritePdo()->withTrashed()->where('email', $email)->exists()) {
            return 'exists';
        }
        $admin = new User();
        $admin->forceFill([
            'name' => 'Administrator', 'email' => $email, 'password' => $password, 'is_admin' => true, 'email_verified_at' => new \DateTimeImmutable(),
        ])->save();
        return 'created';
    }
}
