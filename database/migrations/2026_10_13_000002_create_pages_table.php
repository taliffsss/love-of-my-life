<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('pages', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // random public id: /p/{slug} and the dashboard URLs
            $t->string('slug', 16)->unique();
            // <subdomain>.<saas domain> (Forever plan) and an optional custom host set by an admin
            $t->string('subdomain', 40)->nullable()->unique();
            $t->string('custom_domain', 253)->nullable()->unique();
            $t->string('recipient', 60);
            $t->text('letter');
            $t->string('song_id', 11)->nullable();
            $t->string('song_title', 200)->nullable();
            $t->string('song_artist', 200)->nullable();
            // the question asked before the page opens; the answer is stored hashed
            $t->boolean('gate_enabled');
            $t->string('gate_password')->nullable();
            $t->string('gate_eyebrow', 60);
            $t->string('gate_question', 120);
            $t->string('layout', 20);
            $t->string('theme', 20);
            $t->string('plan', 20);
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->boolean('searchable');
            $t->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('pages');
    }
};
