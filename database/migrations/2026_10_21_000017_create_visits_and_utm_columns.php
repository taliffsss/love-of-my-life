<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// Where the traffic comes from (App\Services\Traffic): one row per visit of a public page by someone who has no row of the same campaign in the last
// 30 minutes, with the UTM tags of the link (or the site they came from), and the first-touch tags kept on the account that signs up afterwards.
// No IP address, no user agent, no name or e-mail: `visitor` is a random id from a first-party cookie.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('visits', function (Blueprint $t): void {
            $t->id();
            $t->string('visitor', 32)->index();
            $t->string('source', 60)->index();
            $t->string('medium', 60);
            $t->string('campaign', 60)->nullable()->index();
            $t->string('content', 60)->nullable();
            $t->string('term', 60)->nullable();
            $t->string('referrer', 100)->nullable();
            $t->string('path', 100);
            $t->timestamp('created_at')->nullable()->index();
        });
        $schema->table('users', function (Blueprint $t): void {
            $t->string('utm_source', 60)->nullable();
            $t->string('utm_medium', 60)->nullable();
            $t->string('utm_campaign', 60)->nullable();
            $t->string('utm_content', 60)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->dropColumn('utm_source', 'utm_medium', 'utm_campaign', 'utm_content');
        });
        $schema->dropIfExists('visits');
    }
};
