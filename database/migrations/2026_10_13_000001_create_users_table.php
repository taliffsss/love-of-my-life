<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 80);
            $t->string('email', 254)->unique();
            $t->string('password');
            $t->boolean('is_admin');
            $t->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('users');
    }
};
