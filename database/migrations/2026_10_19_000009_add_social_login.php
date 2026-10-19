<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// Sign in with Google or Facebook: how an account was made (manual, google or facebook), and the provider identities
// linked to it (one row per provider account; the provider's own id is what identifies it, never the e-mail).
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->string('login_type', 10)->default('manual');
        });
        $schema->create('social_accounts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('provider', 20);
            $t->string('provider_uid', 64);
            $t->timestamps();
            $t->unique(['provider', 'provider_uid']);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('social_accounts');
        $schema->table('users', function (Blueprint $t): void {
            $t->dropColumn('login_type');
        });
    }
};
