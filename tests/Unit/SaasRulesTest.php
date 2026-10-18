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
