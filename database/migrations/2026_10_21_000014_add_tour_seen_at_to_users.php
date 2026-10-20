<?php

declare(strict_types=1);

use Naluz\Database\Connection;
use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// The guided tour on the dashboard is shown once, to an account that has just arrived. `tour_seen_at` is set when it is
// finished or skipped. Accounts that exist already have found their way around: they are marked as having seen it.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->timestamp('tour_seen_at')->nullable();
        });
        app(Connection::class)->table('users')->update(['tour_seen_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function down(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->dropColumn('tour_seen_at');
        });
    }
};
