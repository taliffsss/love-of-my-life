<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use Naluz\Tests\TestCase;

/** The migration that brought billing periods renames the first plans of 3.0 development and records their periods. */
final class PlanMigrationTest extends TestCase
{
    public function testTheFirstPlansBecomeSweetAndSweetheart(): void
    {
        $migration = require base_path('database/migrations/2026_10_18_000005_add_period_to_orders.php');
        $schema = $this->db()->schema();
        $migration->down($schema);

        $owner = $this->makeUser();
        $now = '2026-10-01 00:00:00';
        foreach (['basic', 'plus', 'forever'] as $i => $plan) {
            $page = $this->makePage($owner, ['plan' => $plan]);
            $this->db()->table('orders')->insert(['reference' => 'LM-AAAA-AAA' . ($i + 2), 'user_id' => $owner->id, 'page_id' => $page->id,
                'plan' => $plan, 'amount' => 100, 'currency' => 'PHP', 'status' => 'paid', 'provider' => 'manual',
                'created_at' => $now, 'updated_at' => $now]);
        }
        $migration->up($schema);

        $pages = array_map(static fn ($r) => (array) $r, $this->db()->table('pages')->orderBy('id')->get()->all());
        $orders = array_map(static fn ($r) => (array) $r, $this->db()->table('orders')->orderBy('id')->get()->all());
        $this->assertSame(['sweet', 'sweetheart', 'forever'], array_column($pages, 'plan'));
        $this->assertSame(['sweet', 'sweetheart', 'forever'], array_column($orders, 'plan'));
        $this->assertSame(['year', 'year', 'once'], array_column($orders, 'period'));
    }
}
