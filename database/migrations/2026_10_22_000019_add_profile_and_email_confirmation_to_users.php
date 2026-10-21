<?php

declare(strict_types=1);

use Naluz\Database\Connection;
use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// The account profile (phone, address, profile photo) and e-mail confirmation.
//  - `email_verified_at`: when the address was confirmed (null: not yet). Accounts that exist already are marked as confirmed
//    on their sign-up date, so nobody is locked out; a new manual sign-up has to confirm, a Google/Facebook one is confirmed by the provider.
//  - `verify_code_hash`/`verify_expires_at`/`verify_sent_at`/`verify_attempts`: the 6-digit code e-mailed to confirm an address (App\Services\EmailVerification).
//    `reset_*` are the same for a forgotten password (App\Services\PasswordReset). Only a keyed hash of a code is stored, never the code.
//  - `pending_email`: a new address someone asked for; it replaces `email` once the code sent to it is entered.
//  - `avatar_disk`/`avatar_path`: the profile photo as a storage key (users/<id>/avatar/<random>.<ext>); the URL is made from the key at render time.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->timestamp('email_verified_at')->nullable();
            $t->string('verify_code_hash', 64)->nullable();
            $t->timestamp('verify_expires_at')->nullable();
            $t->timestamp('verify_sent_at')->nullable();
            $t->integer('verify_attempts')->default(0);
            $t->string('reset_code_hash', 64)->nullable();
            $t->timestamp('reset_expires_at')->nullable();
            $t->timestamp('reset_sent_at')->nullable();
            $t->integer('reset_attempts')->default(0);
            $t->string('pending_email', 254)->nullable();
            $t->string('phone', 32)->nullable();
            $t->string('address_line1', 120)->nullable();
            $t->string('address_line2', 120)->nullable();
            $t->string('city', 80)->nullable();
            $t->string('region', 80)->nullable();
            $t->string('postal_code', 20)->nullable();
            $t->string('country', 60)->nullable();
            $t->string('avatar_disk', 20)->nullable();
            $t->string('avatar_path', 500)->nullable();
        });
        app(Connection::class)->table('users')->update(['email_verified_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function down(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->dropColumn(
                'email_verified_at',
                'verify_code_hash',
                'verify_expires_at',
                'verify_sent_at',
                'verify_attempts',
                'reset_code_hash',
                'reset_expires_at',
                'reset_sent_at',
                'reset_attempts',
                'pending_email',
                'phone',
                'address_line1',
                'address_line2',
                'city',
                'region',
                'postal_code',
                'country',
                'avatar_disk',
                'avatar_path'
            );
        });
    }
};
