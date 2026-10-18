<?php

declare(strict_types=1);

use Naluz\Database\Connection;
use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// Plans are sold monthly, yearly or once (config/saas.php `periods`): an order records which. The first plans
// (Basic and Plus, a year each; Forever, once) become Sweet, Sweetheart and Forever; this runs before 3.0 is released,
// so it only matters to databases made while developing it.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('orders', function (Blueprint $t): void {
            $t->string('period', 10)->default('year');
        });
        $db = app(Connection::class); // the connection the migrator runs on (inside its transaction)
        $db->table('orders')->where('plan', '=', 'forever')->update(['period' => 'once']);
        foreach (['basic' => 'sweet', 'plus' => 'sweetheart'] as $old => $new) {
            $db->table('orders')->where('plan', '=', $old)->update(['plan' => $new]);
            $db->table('pages')->where('plan', '=', $old)->update(['plan' => $new]);
        }
    }

    public function down(Schema $schema): void
    {
        $schema->table('orders', function (Blueprint $t): void {
            $t->dropColumn('period');
        });
    }
};
