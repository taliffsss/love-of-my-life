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
        $this->assertSame(['site.css', 'landing.css', 'invitations.css', 'ui.js', 'depth.js', 'landing.js'], array_keys($manifest));
        foreach ($manifest as $name => $entry) {
            $text = implode("\n", array_map(static fn (string $s): string => (string) file_get_contents(base_path($s)), $entry['sources']));
            $this->assertSame($entry['sha256'], hash('sha256', $text), "{$name} is stale: run `node bin/build-assets.mjs` and commit public/assets/dist/");
            $built = (string) file_get_contents(base_path('public/assets/dist/' . $name));
            $this->assertNotSame('', $built, $name);
            $this->assertLessThan(strlen($text), strlen($built), "{$name} is smaller than its sources");
        }
    }

    public function testThePagesLoadTheBuiltFilesAndTheLayoutNoLongerLoadsThreeStylesheets(): void
    {
        foreach (['/', '/invitations', '/login'] as $path) {
            $html = (string) $this->get($path)->getBody();
            $this->assertMatchesRegularExpression('#href="/assets/dist/site\.css\?v=\d+"#', $html, $path);
            $this->assertMatchesRegularExpression('#src="/assets/dist/ui\.js\?v=\d+" defer#', $html, $path);
            foreach (['/assets/ui.css', '/assets/depth.css', '/assets/app.css', '/assets/ui.js'] as $source) {
                $this->assertStringNotContainsString($source, $html, "{$path}: {$source} comes through dist/");
            }
        }
        $this->assertStringContainsString('/assets/dist/landing.css', (string) $this->get('/')->getBody());
        $this->assertStringContainsString('/assets/dist/landing.js', (string) $this->get('/')->getBody());
        $this->assertStringContainsString('/assets/dist/invitations.css', (string) $this->get('/invitations')->getBody());
    }
}
