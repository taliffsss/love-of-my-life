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

        $admins = User::query()->where('email', User::normaliseEmail((string) (env('ADMIN_EMAIL') ?: AdminSeeder::DEFAULT_EMAIL)))->get()->all(); // a developer's .env may set ADMIN_EMAIL
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

    public function testTheBootstrapCreatesTheAdminOnceAndNeverTouchesAnExistingAccount(): void
    {
        $boot = $this->app->make(\App\Services\AdminBootstrap::class);
        $this->assertSame('skipped', $boot->ensure('', ''));
        $this->assertSame('created', $boot->ensure(' Boss@Example.com ', 'a long enough password'));

        $admin = User::query()->where('email', 'boss@example.com')->first();
        $this->assertTrue($admin->is_admin);
        $this->assertNotNull($admin->email_verified_at, 'no code is asked of the administrator');

        $admin->forceFill(['password' => 'changed in the profile'])->save();
        $hash = $admin->fresh()->getRawAttributes()['password'];
        $this->assertSame('exists', $boot->ensure('boss@example.com', 'a different password!'));
        $this->assertSame($hash, $admin->fresh()->getRawAttributes()['password'], 'the next deploy does not reset it');

        $normal = new User();
        $normal->forceFill(['name' => 'N', 'email' => 'normal@example.com', 'password' => 'x', 'is_admin' => false])->save();
        $this->assertSame('exists', $boot->ensure('normal@example.com', 'a long enough password'));
        $this->assertFalse($normal->fresh()->is_admin, 'a normal account is not promoted');
    }

    public function testTheBootstrapRefusesAWeakPasswordOrABadEmail(): void
    {
        $boot = $this->app->make(\App\Services\AdminBootstrap::class);
        foreach ([['boss@example.com', 'short'], ['not-an-email', 'a long enough password']] as [$email, $password]) {
            try {
                $boot->ensure($email, $password);
                $this->fail('should have been refused');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('12 characters', $e->getMessage());
            }
        }
        $this->assertSame(0, User::query()->count());
    }
}
