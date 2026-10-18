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
        return new Plans($config['plans'], 'PHP', $config['periods']);
    }

    private function page(string $plan, string $layout = 'classic'): Page
    {
        $page = new Page();
        $page->setRawAttribute('plan', $plan);
        $page->setRawAttribute('layout', $layout);
        return $page;
    }

    public function testWhatEachPlanCanBuyNext(): void
    {
        $plans = $this->plans();
        $this->assertSame(['sweet', 'sweetheart', 'forever'], array_keys($plans->purchasable($this->page('free'))));
        $this->assertSame(['sweet', 'sweetheart', 'forever'], array_keys($plans->purchasable($this->page('sweet'))), 'renew or upgrade');
        $this->assertSame(['sweetheart', 'forever'], array_keys($plans->purchasable($this->page('sweetheart'))));
        $this->assertSame([], $plans->purchasable($this->page('forever')), 'nothing after Forever');
        $this->assertSame(['month' => 20000, 'year' => 240000], $plans->purchasable($this->page('free'))['sweet']['offers'], 'from ₱200 a month');
        $this->assertSame(['month' => 35000, 'year' => 420000], $plans->purchasable($this->page('sweet'))['sweetheart']['offers']);
        $this->assertSame(['once' => 499900], $plans->purchasable($this->page('sweetheart'))['forever']['offers']);
        $this->assertTrue($plans->canBuy($this->page('sweet'), 'sweet', 'year'), 'a monthly page can renew for a year');
        $this->assertFalse($plans->canBuy($this->page('free'), 'free', 'month'));
        $this->assertFalse($plans->canBuy($this->page('sweetheart'), 'sweet', 'month'));
        $this->assertFalse($plans->canBuy($this->page('free'), 'sweet', 'once'));
        $this->assertFalse($plans->canBuy($this->page('free'), 'forever', 'month'));
    }

    public function testThePeriods(): void
    {
        $plans = $this->plans();
        $this->assertSame([1, 12, null], [$plans->months('month'), $plans->months('year'), $plans->months('once')]);
        $this->assertTrue($plans->isPermanent('forever'));
        $this->assertFalse($plans->isPermanent('sweetheart'));
        foreach (['sweet', 'sweetheart'] as $plan) {
            $this->assertSame(12 * $plans->price($plan, 'month'), $plans->price($plan, 'year'), "a {$plan} year costs twelve months: no free months");
        }
        $this->assertSame(20000, min(array_map(static fn (array $p): int => (int) ($p['prices']['month'] ?? PHP_INT_MAX), $plans->paid())), 'plans start at ₱200 a month');
        $this->expectException(\DomainException::class);
        $plans->months('week');
    }

    public function testPhotosIsNotAPlanKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Plans(['photos' => ['name' => 'Photos', 'prices' => ['once' => 100], 'photos' => 1, 'publish' => true, 'subdomain' => false]]);
    }

    public function testMonthsAreCalendarMonths(): void
    {
        $utc = new \DateTimeZone('UTC');
        $at = static fn (string $s): \DateTimeImmutable => new \DateTimeImmutable($s, $utc);
        $this->assertSame('2026-02-28 09:30', Plans::addMonths($at('2026-01-31 09:30'), 1)->format('Y-m-d H:i'));
        $this->assertSame('2028-02-29 00:00', Plans::addMonths($at('2028-01-31 00:00'), 1)->format('Y-m-d H:i'), 'leap year');
        $this->assertSame('2026-09-30 00:00', Plans::addMonths($at('2026-08-31 00:00'), 1)->format('Y-m-d H:i'));
        $this->assertSame('2027-10-13 12:00', Plans::addMonths($at('2026-10-13 12:00'), 12)->format('Y-m-d H:i'));
        $this->assertSame('2027-01-15 08:00', Plans::addMonths($at('2026-12-15 08:00'), 1)->format('Y-m-d H:i'), 'into the next year');
    }

    public function testMoneyIsShownInPesos(): void
    {
        $this->assertSame('₱99', Plans::money(9900));
        $this->assertSame('₱1,499', Plans::money(149900));
        $this->assertSame('₱99.50', Plans::money(9950));
        $this->assertSame('USD 5', Plans::money(500, 'USD'));
        $this->assertSame(['a month', 'a year', 'once'], [Plans::per('month'), Plans::per('year'), Plans::per('once')]);
    }

    public function testSweetheartAndForeverIncludeAnAddress(): void
    {
        $this->assertSame(['sweetheart', 'forever'], array_keys(array_filter($this->plans()->all(), static fn (array $p): bool => $p['subdomain'])));
    }

    public function testWhichTemplatesEachPlanIncludes(): void
    {
        $plans = $this->plans();
        foreach (['classic', 'cinematic', 'polaroid', 'envelope', 'timeline'] as $key) {
            $this->assertTrue($plans->allowsTemplate('sweet', $key), $key);
        }
        foreach (['scratch', 'storybook', 'memory', 'gift', 'stars'] as $key) {
            $this->assertFalse($plans->allowsTemplate('sweet', $key), $key);
            $this->assertSame(['sweetheart', 'forever'], $plans->plansWithTemplate($key), $key);
        }
        foreach (array_keys(Designs::LAYOUTS) as $key) {
            $this->assertTrue($plans->allowsTemplate('sweetheart', $key), $key);
            $this->assertTrue($plans->allowsTemplate('forever', $key), $key);
            $this->assertTrue($plans->allowsTemplate('free', $key), "{$key}: the free preview tries everything");
        }
        $this->assertSame('classic', $plans->templateFor($this->page('sweet', 'stars')), 'visitors of a Sweet page see a template of Sweet');
        $this->assertSame('envelope', $plans->templateFor($this->page('sweet', 'envelope')));
        $this->assertSame('stars', $plans->templateFor($this->page('sweetheart', 'stars')));
        $this->assertSame('Sweetheart and Forever', $plans->names(['sweetheart', 'forever']));
        $this->assertSame('Sweet, Sweetheart and Forever', $plans->names(['sweet', 'sweetheart', 'forever']));
        $this->assertSame('Forever', $plans->names(['forever']));
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
        $this->assertCount(10, Designs::LAYOUTS, 'ten templates');
        $this->assertGreaterThanOrEqual(3, count(Designs::THEMES));
        $this->assertSame('polaroid', Designs::layout('polaroid'));
        $this->assertSame('stars', Designs::layout('stars'));
        $this->assertSame('classic', Designs::layout('../../x'));
        $this->assertSame('classic', Designs::layout(null));
        $this->assertSame('rose', Designs::theme('neon'));
        $this->assertSame('slideshow', Designs::view('cinematic'), 'the three slideshow templates share their markup');
        $this->assertSame('envelope', Designs::view('envelope'));
        $this->assertSame('slideshow', Designs::view('../../x'), 'an unknown template falls back to classic');
        $this->assertSame(['css' => 'assets/templates/gift.css', 'js' => 'assets/templates/gift.js'], Designs::assets('gift'));
        $this->assertSame(['css' => 'assets/templates/classic.css', 'js' => 'assets/templates/classic.js'], Designs::assets('<x>'));
    }

    /** @return array<string,array{string}> */
    public static function templates(): array
    {
        return array_combine(array_keys(Designs::LAYOUTS), array_map(static fn (string $key): array => [$key], array_keys(Designs::LAYOUTS)));
    }

    /** A template is a module: its entry, its own CSS and script, and its markup. */
    #[DataProvider('templates')]
    public function testEveryTemplateIsAModuleOfItsOwn(string $key): void
    {
        $root = dirname(__DIR__, 2);
        $entry = Designs::LAYOUTS[$key];
        $this->assertMatchesRegularExpression('/^[a-z]{3,20}$/', $key, 'fits the 20-character layout column');
        foreach (['name', 'description', 'play'] as $field) {
            $this->assertNotSame('', trim($entry[$field]), $field);
        }
        $this->assertContains($entry['kind'], ['slideshow', 'interactive']);

        $css = (string) @file_get_contents($root . '/public/' . Designs::assets($key)['css']);
        $this->assertStringContainsString('body[data-layout="' . $key . '"]', $css, 'its rules are scoped to the template');
        $this->assertStringNotContainsString('data-theme', $css, 'colours come from the theme variables, so every theme fits');

        $js = (string) @file_get_contents($root . '/public/' . Designs::assets($key)['js']);
        $this->assertStringContainsString('(window.LoveTemplates = window.LoveTemplates || {}).' . $key . ' = {', $js, 'registers itself under its key');
        $this->assertStringContainsString('build', $js);

        $this->assertFileExists($root . '/resources/views/site/templates/' . Designs::view($key) . '.naluz.php');
        $this->assertStringContainsString('.schem-' . $key, (string) file_get_contents($root . '/public/assets/ui.css'), 'a thumbnail for the picker');
    }

    public function testTheInteractiveTemplatesNeverPutTheContentInTheirMarkup(): void
    {
        foreach (array_keys(Designs::LAYOUTS) as $key) {
            $view = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/site/templates/' . Designs::view($key) . '.naluz.php');
            $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $view);
            // only the name is known before the answer; the letter, the photos and the song arrive after it
            foreach (['letter', 'photos', 'paragraphs', 'song', '$content'] as $secret) {
                $this->assertDoesNotMatchRegularExpression('/\{\{[^}]*' . preg_quote($secret, '/') . '/', $view, "{$key} renders {$secret}");
            }
            $this->assertStringContainsString("@include('site.partials.music')", $view, "{$key} has the music controls site.js needs");
        }
    }

    public function testEveryThemeIsABlockOfVariables(): void
    {
        $themes = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/themes.css');
        $site = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/site.css');
        foreach (Designs::THEMES as $key => $theme) {
            $this->assertStringContainsString('body[data-theme="' . $key . '"]', $themes, $key);
            $this->assertContains($theme['scheme'], ['light', 'dark'], $key);
            $this->assertCount(3, $theme['colors']);
        }
        $this->assertStringNotContainsString('data-theme', $site, 'the shared styles never single out a theme; a light one is data-scheme');
        $this->assertStringContainsString('body[data-scheme="light"] .card', $site);
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
        // a missing or broken template still opens on the letter and the photos
        $this->assertStringContainsString('return plain(content);', $js);
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
