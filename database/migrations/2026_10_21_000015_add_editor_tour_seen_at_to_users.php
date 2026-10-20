<?php

declare(strict_types=1);

use Naluz\Database\Connection;
use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// The page editor has its own tour (see add_tour_seen_at_to_users, which is the dashboard's), shown the first time an account opens an
// editor. Accounts that exist already know the editor: they are marked as having seen it.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->timestamp('editor_tour_seen_at')->nullable();
        });
        app(Connection::class)->table('users')->update(['editor_tour_seen_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function down(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->dropColumn('editor_tour_seen_at');
        });
    }
};
