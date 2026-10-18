<?php

declare(strict_types=1);

namespace Database\Seeders;

use Naluz\Database\Seeder;

/** `php naluz db:seed`: the administrator always; the demo account only outside production. */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        if ((string) config('app.env') !== 'production') {
            $this->call(DemoSeeder::class);
        }
    }
}
