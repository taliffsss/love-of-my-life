<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// Paying with GCash, PayMaya or a card, and subscribing.
//  - orders: how the customer chose to pay (`method`: gcash, paymaya or card), whether it is a one-time payment or part of a
//    subscription (`billing`), and the subscription a renewal belongs to.
//  - payment_attempts: every payment started for an order. A customer may change method and start another, and the old one
//    can still complete, so a payment is matched to its order through any of its attempts. `redirect_url` is where an
//    e-wallet sends the customer to approve it; `subscription_ref` is the provider's subscription when the first payment
//    starts one that the provider will keep charging.
//  - subscriptions: a plan that keeps renewing. `mode` is `auto` (the provider charges the customer: `provider_ref` is its
//    subscription and `last_invoice_ref` the last invoice already counted) or `manual` (a renewal order the customer pays
//    again); the page's own expiry says when the next period is due.
//  - provider_refs: the provider's ids that are made once and reused (a customer per user, a plan per price).
// What identifies a payment at the provider is stored encrypted (App\Services\PaymentVault), hence the text columns.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('orders', function (Blueprint $t): void {
            $t->string('method', 10)->nullable();
            $t->string('billing', 12)->default('one_time');
            $t->bigInteger('subscription_id')->nullable()->index();
        });
        $schema->create('payment_attempts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $t->string('provider', 20);
            // sealed (encrypted) values: App\Services\PaymentVault; provider_ref_idx is the blind index it is looked up and made unique by
            $t->text('provider_ref');
            $t->string('provider_ref_idx', 64)->unique();
            $t->string('method', 10);
            $t->string('billing', 12);
            $t->text('redirect_url')->nullable();
            $t->text('subscription_ref')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        $schema->create('subscriptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->bigInteger('page_id')->nullable()->index();
            $t->string('plan', 20);
            $t->string('period', 10);
            $t->integer('amount');
            $t->string('currency', 3);
            $t->string('method', 10);
            $t->string('provider', 20);
            $t->string('mode', 8)->default('manual');
            $t->text('provider_ref')->nullable();
            $t->text('last_invoice_ref')->nullable();
            $t->string('status', 12)->default('active')->index();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });
        $schema->create('provider_refs', function (Blueprint $t): void {
            $t->id();
            $t->string('provider', 20);
            $t->string('kind', 12);
            $t->string('ref_key', 80);
            $t->text('ref'); // sealed
            $t->timestamps();
            $t->unique(['provider', 'kind', 'ref_key']);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->dropIfExists('provider_refs');
        $schema->dropIfExists('subscriptions');
        $schema->dropIfExists('payment_attempts');
        $schema->table('orders', function (Blueprint $t): void {
            $t->dropColumn('method', 'billing', 'subscription_id');
        });
    }
};
