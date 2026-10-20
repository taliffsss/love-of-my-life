<?php

declare(strict_types=1);

use Naluz\Database\Migrations\Migration;
use Naluz\Database\Schema\Blueprint;
use Naluz\Database\Schema\Schema;

// The plan someone picked on the pricing section before they had an account (config/saas.php key), kept with the account made
// next. It only narrows what the dashboard shows (App\Services\IntendedPlan); buying is still done on a page.
return new class extends Migration {
    public function up(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->string('intended_plan', 20)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('users', function (Blueprint $t): void {
            $t->dropColumn('intended_plan');
        });
    }
};
