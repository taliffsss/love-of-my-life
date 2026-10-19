<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// An administrator can deactivate or suspend an account: it keeps its data but cannot sign in. `status_reason` is shown to
// the person when a suspended account tries to sign in.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->string('status', 12)->default('active');
            $t->string('status_reason', 200)->nullable();
            $t->timestamp('status_changed_at')->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->dropColumn('status', 'status_reason', 'status_changed_at');
        });
    }
};
