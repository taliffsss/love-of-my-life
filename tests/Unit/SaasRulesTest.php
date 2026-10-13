<?php

declare(strict_types=1);

namespace Naluz\Tests\Unit;

use App\Models\Page;
use App\Services\Designs;
use App\Services\Plans;
use App\Services\Subdomains;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The pure rules: plan catalogue and upgrades, subdomain names, designs. */
final class SaasRulesTest extends TestCase
{
    private function plans(): Plans
    {
        $config = require dirname(__DIR__, 2) . '/config/saas.php';
        return new Plans($config['plans'], 'PHP');
    }

    private function page(string $plan): Page
    {
        $page = new Page();
        $page->setRawAttribute('plan', $plan);
        return $page;
    }

    public function testWhatEachPlanCanBuyNext(): void
    {
        $plans = $this->plans();
        $this->assertSame(['basic', 'plus', 'forever'], array_keys($plans->purchasable($this->page('free'))));
        $this->assertSame(['basic', 'plus', 'forever'], array_keys($plans->purchasable($this->page('basic'))), 'renew or upgrade');
        $this->assertSame(['plus', 'forever'], array_keys($plans->purchasable($this->page('plus'))));
        $this->assertSame([], $plans->purchasable($this->page('forever')));
        $this->assertFalse($plans->canBuy($this->page('free'), 'free'));
        $this->assertFalse($plans->canBuy($this->page('plus'), 'basic'));
    }

    public function testMoneyIsShownInPesos(): void
    {
        $this->assertSame('₱99', Plans::money(9900));
        $this->assertSame('₱1,250', Plans::money(125000));
        $this->assertSame('₱99.50', Plans::money(9950));
        $this->assertSame('USD 5', Plans::money(500, 'USD'));
    }

    public function testOnlyForeverIncludesASubdomain(): void
    {
        $this->assertSame(['forever'], array_keys(array_filter($this->plans()->all(), static fn (array $p): bool => $p['subdomain'])));
    }

    /** @return array<string,array{string,string}> */
    public static function typedNames(): array
    {
        return [
            'plain' => ['jinelin', 'jinelin'],
            'case and spaces' => ['  JineLin ', 'jinelin'],
            'whole address' => ['jinelin.love.test', 'jinelin'],
            'with scheme' => ['https://jinelin.love.test/', 'jinelin'],
        ];
    }

    #[DataProvider('typedNames')]
    public function testNamesAreNormalised(string $typed, string $expected): void
    {
        $this->assertSame($expected, (new Subdomains('love.test'))->normalise($typed));
    }

    /** @return array<string,array{string,bool}> */
    public static function names(): array
    {
        return [
            'letters' => ['jinelin', true],
            'with dash and digits' => ['yam-and-me-2026', true],
            'three chars' => ['abc', true],
            'thirty chars' => [str_repeat('a', 30), true],
            'too short' => ['ab', false],
            'too long' => [str_repeat('a', 31), false],
            'leading dash' => ['-abc', false],
            'trailing dash' => ['abc-', false],
            'double dash (punycode)' => ['xn--abc', false],
            'dot' => ['a.b.c', false],
            'underscore' => ['a_bc', false],
            'accent' => ['jinélin', false],
            'reserved' => ['www', false],
            'reserved admin' => ['admin', false],
        ];
    }

    #[DataProvider('names')]
    public function testNameRules(string $name, bool $ok): void
    {
        $rules = new Subdomains('love.test', ['www', 'admin']);
        $this->assertSame($ok, $rules->problem($name) === null, (string) $rules->problem($name));
    }

    public function testEveryReservedNameIsAValidLabelSoTheRuleMatters(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/saas.php';
        $this->assertContains('www', $config['reserved_subdomains']);
        $this->assertContains('dashboard', $config['reserved_subdomains']);
        $this->assertContains('api', $config['reserved_subdomains']);
    }

    public function testDesignsFallBackToTheDefaults(): void
    {
        $this->assertCount(3, Designs::LAYOUTS);
        $this->assertGreaterThanOrEqual(3, count(Designs::THEMES));
        $this->assertSame('polaroid', Designs::layout('polaroid'));
        $this->assertSame('classic', Designs::layout('../../x'));
        $this->assertSame('classic', Designs::layout(null));
        $this->assertSame('rose', Designs::theme('neon'));
    }

    public function testEveryLayoutAndThemeHasCss(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/site.css');
        foreach (array_keys(Designs::LAYOUTS) as $layout) {
            if ($layout !== 'classic') {
                $this->assertStringContainsString('body[data-layout="' . $layout . '"]', $css, $layout);
            }
        }
        foreach (array_keys(Designs::THEMES) as $theme) {
            $this->assertStringContainsString('body[data-theme="' . $theme . '"]', $css, $theme);
        }
    }

    public function testThePageScriptKeepsTheCarouselLessons(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/site.js');
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/site.css');
        // 2.3.1: a slide only fades in once its photo is decoded; a slow or broken photo is skipped
        $this->assertStringContainsString('if (await ready(slides[idx]))', $js);
        $this->assertStringContainsString('READY_MAX_MS', $js);
        // 2.2.2: the first photo waits behind the blur; the timer starts when the blur clears
        $this->assertStringContainsString('restart(BLUR_CLEAR_MS)', $js);
        // 2.3.2: the blur belongs to the lock screen only; no blurred copy per slide
        $this->assertStringContainsString('.show.locked {', $css);
        $this->assertStringNotContainsString('img.bg', $js);
        // the answer is never in the page
        $this->assertStringNotContainsString('PASSWORD', $js);
        $this->assertStringContainsString('fetch(CONFIG.unlockUrl', $js);
    }

    public function testPasswordsAreHashedWithABoundedMemoryCost(): void
    {
        $app = new \Naluz\Foundation\Application(dirname(__DIR__, 2), ['app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);
        $app->boot();
        $info = password_get_info($app->make(\Naluz\Security\Hasher::class)->make('yam'));
        $this->assertSame('argon2id', $info['algoName']);
        $this->assertSame(19456, $info['options']['memory_cost'], '19 MiB, not PHP\'s 64 MiB default');
        $this->assertSame(2, $info['options']['time_cost']);
    }
}
