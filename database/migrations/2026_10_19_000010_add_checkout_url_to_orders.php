<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// A hosted checkout (PayMongo, Xendit) is created once per order: its address is kept so a customer who comes back to a
// pending order is sent to the same checkout instead of getting a second one.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('orders', function (Blueprint $t): void {
            $t->string('checkout_url', 1000)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('orders', function (Blueprint $t): void {
            $t->dropColumn('checkout_url');
        });
    }
};
