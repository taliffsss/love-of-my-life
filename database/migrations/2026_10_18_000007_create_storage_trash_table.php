<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// Files whose photo is gone. A row is written in the same transaction that deletes the photo, and removed once the
// file is deleted from storage, so a failed or interrupted delete is retried (bin/empty-trash.php) instead of leaving
// a file nobody pays for.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('storage_trash', function (Blueprint $t): void {
            $t->id();
            $t->string('disk', 20);
            $t->string('path', 500);
            $t->integer('attempts')->default(0);
            $t->string('last_error', 500)->nullable();
            $t->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('storage_trash');
    }
};
