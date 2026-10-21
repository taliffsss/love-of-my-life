<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Naluz\Database\Seeder;

/**
 * The administrator account (users.is_admin). Safe to run again.
 *
 *   ADMIN_EMAIL     default admin@lovable.local
 *   ADMIN_PASSWORD  default admin1234 on local/testing/development machines; REQUIRED (12+ characters) anywhere else.
 *                   When it is set, it is applied to an existing admin too; when it is not, an existing admin keeps
 *                   the password it has.
 */
final class AdminSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'admin@lovable.local';
    public const DEFAULT_PASSWORD = 'admin1234';

    public function run(): void
    {
        $email = User::normaliseEmail((string) (env('ADMIN_EMAIL') ?: self::DEFAULT_EMAIL));
        $password = (string) env('ADMIN_PASSWORD', '');
        $local = in_array((string) config('app.env'), ['local', 'testing', 'development'], true);

        if ($password === '' && !$local) {
            throw new \RuntimeException('Set ADMIN_PASSWORD (12 or more characters) to seed the administrator outside a local environment.');
        }
        if ($password !== '' && !$local && mb_strlen($password) < 12) {
            throw new \RuntimeException('ADMIN_PASSWORD must have at least 12 characters outside a local environment.');
        }

        $admin = User::query()->where('email', $email)->first();
        if ($admin === null) {
            $admin = new User();
            $admin->forceFill(['name' => 'Administrator', 'email' => $email, 'password' => $password !== '' ? $password : self::DEFAULT_PASSWORD, 'is_admin' => true, 'email_verified_at' => new \DateTimeImmutable()])->save();
            return;
        }
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => $admin->email_verified_at ?? new \DateTimeImmutable()] + ($password !== '' ? ['password' => $password] : []))->save();
    }
}
