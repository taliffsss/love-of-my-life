<?php

declare(strict_types=1);

use App\Services\PaymentVault;
use Naluz\Database\Connection;
use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// Encrypts what identifies an order's payment at the provider (orders.provider_ref and checkout_url), see App\Services\PaymentVault.
// An encrypted value is longer, cannot be searched and cannot be unique, so the two columns are replaced by text columns and a
// provider_ref_idx (blind index) takes over provider_ref's uniqueness. Existing values are sealed in the same step.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $db = app(Connection::class); // the connection the migrator runs on (inside its transaction)
        $old = $db->table('orders')->select('id', 'provider_ref', 'checkout_url')->get()->all();

        $db->statement('DROP INDEX IF EXISTS orders_provider_ref_unique');
        $schema->table('orders', function (Blueprint $t): void {
            $t->dropColumn('provider_ref', 'checkout_url');
        });
        $schema->table('orders', function (Blueprint $t): void {
            $t->text('provider_ref')->nullable();
            $t->text('checkout_url')->nullable();
            $t->string('provider_ref_idx', 64)->nullable()->unique();
        });

        foreach ($old as $row) {
            $ref = $row['provider_ref'] === null || $row['provider_ref'] === '' ? null : (string) $row['provider_ref'];
            $db->table('orders')->where('id', '=', $row['id'])->update([
                'provider_ref' => PaymentVault::seal($ref),
                'provider_ref_idx' => PaymentVault::index($ref),
                'checkout_url' => PaymentVault::seal($row['checkout_url'] === null ? null : (string) $row['checkout_url']),
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        // the plain values are not kept: a rollback leaves the columns empty rather than writing secrets back in the clear
        $schema->table('orders', function (Blueprint $t): void {
            $t->dropColumn('provider_ref_idx');
        });
    }
};
