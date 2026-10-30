<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use Naluz\Tests\TestCase;

/**
 * The public pages load merged, minified copies of the CSS and JS (public/assets/dist/, built by `node bin/build-assets.mjs`).
 * The sources stay the ones people edit, so this fails the moment one changes without a rebuild.
 */
final class AssetsBuildTest extends TestCase
{
    public function testEveryBuiltFileIsUpToDateWithItsSources(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path('public/assets/dist/MANIFEST.json')), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['site.css', 'home.css', 'page.css', 'ui.js', 'ui-public.js', 'depth.js', 'landing.js'], array_keys($manifest));
        foreach ($manifest as $name => $entry) {
            $text = implode("\n", array_map(static fn (string $s): string => (string) file_get_contents(base_path($s)), $entry['sources']));
            $this->assertSame($entry['sha256'], hash('sha256', $text), "{$name} is stale: run `node bin/build-assets.mjs` and commit public/assets/dist/");
            $built = (string) file_get_contents(base_path('public/assets/dist/' . $name));
            $this->assertNotSame('', $built, $name);
            $this->assertLessThan(strlen($text), strlen($built), "{$name} is smaller than its sources");
        }
    }

    public function testThePublicBundlesLeaveOutTheSignedInPagesCodeButKeepTheDemoWindow(): void
    {
        $site = (string) file_get_contents(base_path('public/assets/dist/site.css'));
        foreach (['home.css', 'page.css'] as $name) {
            $public = (string) file_get_contents(base_path('public/assets/dist/' . $name));
            foreach (['.danger-zone', '.tour-pop', '.admin-tabs'] as $dashboardOnly) {
                $this->assertStringContainsString($dashboardOnly, $site, $dashboardOnly);
                $this->assertStringNotContainsString($dashboardOnly, $public, "{$name} {$dashboardOnly}");
            }
            $this->assertStringContainsString('dialog.modal', $public, 'the landing page opens its demo in a modal');
            $this->assertStringContainsString('.toast', $public, 'a flash message can appear on any page');
            $this->assertStringContainsString('.lp-hero', $public, "{$name} carries landing.css");
        }
        $this->assertStringContainsString('.inv-card', (string) file_get_contents(base_path('public/assets/dist/page.css')), 'invitations.css is in page.css');
        $this->assertStringNotContainsString('.inv-card', (string) file_get_contents(base_path('public/assets/dist/home.css')));

        $ui = (string) file_get_contents(base_path('public/assets/dist/ui.js'));
        $lean = (string) file_get_contents(base_path('public/assets/dist/ui-public.js'));
        $this->assertLessThan(strlen($ui) * 0.15, strlen($lean), 'only the helpers, the toast, copy, the landing header and dialogs');
        $this->assertStringContainsString('on-light', $lean, 'the landing header');
        $this->assertStringContainsString('data-copy', $lean);
        $this->assertStringNotContainsString('tour-steps', $lean);
        $this->assertStringContainsString('tour-steps', $ui);
    }

    public function testEachKindOfPageLoadsItsOwnBuiltFilesAndNothingElseOfTheSources(): void
    {
        $expect = ['/' => ['home', 'ui-public'], '/invitations' => ['page', 'ui-public'], '/birthday' => ['page', 'ui-public'], '/long-distance' => ['page', 'ui-public'], '/login' => ['site', 'ui']];
        foreach ($expect as $path => [$css, $js]) {
            $html = (string) $this->get($path)->getBody();
            $this->assertSame(1, preg_match_all('#<link rel="stylesheet" href="/assets/#', $html), "{$path}: one stylesheet of its own");
            $this->assertMatchesRegularExpression('#href="/assets/dist/' . $css . '\.css\?v=\d+"#', $html, $path);
            $this->assertMatchesRegularExpression('#src="/assets/dist/' . $js . '\.js\?v=\d+" defer#', $html, $path);
            foreach (['/assets/ui.css', '/assets/depth.css', '/assets/app.css', '/assets/ui.js', '/assets/landing.css', '/assets/dist/landing.css', '/assets/dist/public.css'] as $source) {
                $this->assertStringNotContainsString($source, $html, "{$path}: {$source}");
            }
        }
        $this->assertMatchesRegularExpression('#src="/assets/dist/landing\.js\?v=\d+" defer#', (string) $this->get('/')->getBody());
    }
}
