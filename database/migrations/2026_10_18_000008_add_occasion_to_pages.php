<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// The occasion a page is for (App\Services\Designs::OCCASIONS): it dresses the page's template in that occasion's
// style. Null for the pages made before occasions, which keep the plain look.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('pages', function (Blueprint $t): void {
            $t->string('occasion', 20)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('pages', function (Blueprint $t): void {
            $t->dropColumn('occasion');
        });
    }
};
