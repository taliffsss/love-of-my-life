<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('photos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            // the disk and key the file was stored under; both null for a photo that lives elsewhere (imported)
            $t->string('disk', 20)->nullable();
            $t->string('path', 500)->nullable();
            $t->string('url', 1000);
            $t->integer('position');
            $t->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('photos');
    }
};
