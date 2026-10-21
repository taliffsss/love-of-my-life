<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// Deleting is always soft: an account, a page or a photo that someone deletes gets `deleted_at` and disappears from the product, but the row (and the file) stays,
// so it can be restored (bin/trash.php). Rows deleted together (a page with its photos, an account with its pages) share one timestamp, so restoring the
// parent brings back exactly what went with it and not what had been deleted earlier.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        foreach (['users', 'pages', 'photos'] as $table) {
            $schema->table($table, function (Blueprint $t): void {
                $t->timestamp('deleted_at')->nullable()->index();
            });
        }
    }

    public function down(Schema $schema): void
    {
        foreach (['users', 'pages', 'photos'] as $table) {
            $schema->table($table, function (Blueprint $t): void {
                $t->dropColumn('deleted_at');
            });
        }
    }
};
