<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// A photo pack is an order too (plan "photos", bought once): `photos` is how many photos it adds to the account's
// allowance once paid. Plan orders add none.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('orders', function (Blueprint $t): void {
            $t->integer('photos')->default(0);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('orders', function (Blueprint $t): void {
            $t->dropColumn('photos');
        });
    }
};
