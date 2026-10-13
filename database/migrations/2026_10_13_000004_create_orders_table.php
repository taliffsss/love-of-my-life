<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('orders', function (Blueprint $t): void {
            $t->id();
            // shown to the customer and quoted when paying manually
            $t->string('reference', 20)->unique();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // kept (set to null) when the page is deleted: the order stays on record
            $t->bigInteger('page_id')->nullable()->index();
            $t->string('plan', 20);
            $t->integer('amount');
            $t->string('currency', 3);
            $t->string('status', 12)->index();
            $t->string('provider', 20);
            $t->string('provider_ref', 120)->nullable()->unique();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('orders');
    }
};
