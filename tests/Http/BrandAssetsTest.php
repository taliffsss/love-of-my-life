<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use Naluz\Tests\TestCase;

/** The logo and icons (made by marketing/brand/build_brand.py) exist, have the sizes the pages and the manifest promise, and are linked. */
final class BrandAssetsTest extends TestCase
{
    private const PNGS = [
        'apple-touch-icon.png' => [180, 180],
        'icons/favicon-16.png' => [16, 16],
        'icons/favicon-32.png' => [32, 32],
        'icons/favicon-48.png' => [48, 48],
        'icons/icon-192.png' => [192, 192],
        'icons/icon-512.png' => [512, 512],
        'icons/icon-maskable-192.png' => [192, 192],
        'icons/icon-maskable-512.png' => [512, 512],
        'icons/mstile-150.png' => [150, 150],
        'og-image.png' => [1200, 630],
    ];

    private function path(string $file): string
    {
        return base_path('public/' . ltrim($file, '/'));
    }

    public function testEveryPngHasItsSize(): void
    {
        foreach (self::PNGS as $file => [$w, $h]) {
            $this->assertFileExists($this->path($file), $file);
            $size = getimagesize($this->path($file));
            $this->assertSame([$w, $h], [$size[0], $size[1]], $file);
            $this->assertSame('image/png', $size['mime'], $file);
        }
        $logo = getimagesize($this->path('logo.png'));
        $this->assertSame(1200, $logo[0]);
        $this->assertGreaterThan(100, $logo[1]);
    }

    public function testTheIcoHoldsTheThreeUsualSizes(): void
    {
        $ico = (string) file_get_contents($this->path('favicon.ico'));
        ['reserved' => $reserved, 'type' => $type, 'count' => $count] = unpack('vreserved/vtype/vcount', $ico);
        $this->assertSame([0, 1, 3], [$reserved, $type, $count], 'an icon file with three images');
        $sizes = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = unpack('Cw/Ch', substr($ico, 6 + $i * 16, 2));
            $sizes[] = $entry['w'];
        }
        sort($sizes);
        $this->assertSame([16, 32, 48], $sizes);
    }

    public function testMaskableIconsAreFullBleedAndTheRoundedOnesAreNot(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('needs GD to read pixels');
        }
        $corner = static function (string $file): int {
            $image = imagecreatefrompng($file);
            return (imagecolorat($image, 0, 0) >> 24) & 0x7F; // 0 = opaque, 127 = transparent
        };
        $this->assertSame(0, $corner($this->path('icons/icon-maskable-512.png')), 'maskable: the background reaches the corners');
        $this->assertSame(0, $corner($this->path('apple-touch-icon.png')), 'iOS fills a transparent corner with black');
        $this->assertSame(127, $corner($this->path('icons/icon-512.png')), 'the "any" icon has rounded, transparent corners');
        $this->assertSame(127, $corner($this->path('icons/favicon-32.png')), 'a favicon is the heart alone');
    }

    public function testTheSvgsAreWellFormedAndTheLogoCarriesItsName(): void
    {
        foreach (['logo.svg', 'logo-dark.svg', 'logo-mark.svg', 'favicon.svg', 'safari-pinned-tab.svg'] as $file) {
            $xml = simplexml_load_string((string) file_get_contents($this->path($file)));
            $this->assertNotFalse($xml, "$file is XML");
            $this->assertSame('svg', $xml->getName());
            $this->assertNotSame('', (string) $xml['viewBox'], "$file has a viewBox");
            $this->assertSame('Love of My Life', (string) $xml->children('http://www.w3.org/2000/svg')->title, "$file is titled");
        }
        $light = (string) file_get_contents($this->path('logo.svg'));
        $dark = (string) file_get_contents($this->path('logo-dark.svg'));
        $this->assertStringContainsString('fill="#2a1621"', $light, 'dark words for a light background');
        $this->assertStringContainsString('fill="#fbeef0"', $dark, 'light words for a dark background');
        $this->assertStringNotContainsString('<text', $light, 'the words are outlines: no font needed anywhere');
    }

    public function testTheManifestPointsAtRealIconsOfTheDeclaredSize(): void
    {
        $manifest = $this->decode($this->get('/manifest.webmanifest'));
        $purposes = [];
        foreach ($manifest['icons'] as $icon) {
            $file = $this->path($icon['src']);
            $this->assertFileExists($file, $icon['src']);
            if ($icon['type'] === 'image/png') {
                $this->assertSame($icon['sizes'], implode('x', array_slice(getimagesize($file), 0, 2)), $icon['src']);
            }
            $purposes[$icon['purpose']][] = $icon['sizes'];
        }
        $this->assertContains('192x192', $purposes['any']);
        $this->assertContains('512x512', $purposes['any']);
        $this->assertSame(['192x192', '512x512'], $purposes['maskable']);
        foreach ($manifest['shortcuts'] as $shortcut) {
            $this->assertStringStartsWith('/', $shortcut['url']);
        }
    }

    public function testEveryKindOfPageLinksTheIconsAndTheCrawlersFindTheLogo(): void
    {
        foreach (['/', '/login', '/demo'] as $uri) {
            $html = (string) $this->get($uri)->getBody();
            foreach (['href="/favicon.ico?v=', 'href="/favicon.svg?v=', 'sizes="32x32"', 'sizes="16x16"', 'rel="apple-touch-icon"', 'rel="mask-icon"', 'name="msapplication-config"'] as $needle) {
                $this->assertStringContainsString($needle, $html, "$uri has $needle");
            }
        }

        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('<meta property="og:image" content="https://love.test/og-image.png" />', $html);
        $this->assertStringContainsString('og:image:width" content="1200"', $html);
        $this->assertStringContainsString('og:image:alt', $html);
        $this->assertStringContainsString('name="twitter:image" content="https://love.test/og-image.png"', $html);

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m), 'structured data on the landing page');
        $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR); // the \u00xx escapes are plain JSON
        $graph = array_column($data['@graph'], null, '@type');
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('https://love.test/icons/icon-512.png', $graph['Organization']['logo']['url']);
        $this->assertFileExists($this->path('icons/icon-512.png'), 'the logo URL is a real file');
        $this->assertSame('https://love.test/', $graph['WebSite']['url']);
        $this->assertSame($graph['Organization']['@id'], $graph['WebSite']['publisher']['@id']);
    }

    public function testTheBrandMarkInThePagesIsTheHeartWithAKeyhole(): void
    {
        $html = (string) $this->get('/')->getBody();
        $this->assertStringContainsString('viewBox="6 7 52 50"', $html);
        $this->assertStringNotContainsString('M12 21s-8-4.900', $html, 'the old plain heart is gone');
    }
}
