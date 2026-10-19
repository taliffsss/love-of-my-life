<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Models\Page;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\DemoSeeder;
use Naluz\Tests\TestCase;

final class SeedersTest extends TestCase
{
    public function testTheAdminSeederMakesOneAdminAndCanRunAgain(): void
    {
        $this->app->make(AdminSeeder::class)->run();
        $this->app->make(AdminSeeder::class)->run();

        $admins = User::query()->where('email', AdminSeeder::DEFAULT_EMAIL)->get()->all();
        $this->assertCount(1, $admins);
        $this->assertTrue($admins[0]->is_admin);
    }

    public function testTheDemoSeederMakesTheAccountAndOnePagePerOccasionOnce(): void
    {
        $this->app->make(DemoSeeder::class)->run();
        $this->app->make(DemoSeeder::class)->run();

        $demo = User::query()->where('email', DemoSeeder::EMAIL)->first();
        $this->assertInstanceOf(User::class, $demo);
        $this->assertFalse($demo->is_admin);
        $pages = Page::query()->where('user_id', $demo->id)->get()->all();
        $this->assertSame(['anniversary', 'birthday', 'invitation', 'partner', 'wedding'], $this->sorted(array_map(static fn (Page $p) => $p->occasion, $pages)));
    }

    /** @param list<string> $values @return list<string> */
    private function sorted(array $values): array
    {
        sort($values);
        return $values;
    }
}
