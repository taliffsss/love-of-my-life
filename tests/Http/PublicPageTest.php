<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Page;
use App\Services\Designs;
use App\Models\User;
use Naluz\Security\Hasher;
use Naluz\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

final class PublicPageTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
    }

    private function lockedPage(array $attributes = []): Page
    {
        $page = $this->makeLivePage($this->owner, $attributes + [
            'gate_enabled' => true,
            'gate_password' => $this->app->make(Hasher::class)->make('yam'),
            'gate_question' => 'What is your endearment?',
            'letter' => ['A secret paragraph only she should read.'],
        ]);
        $this->makePhoto($page, 'https://cdn.test/secret-photo.jpg');
        return $page;
    }

    private function unlock(Page $page, string $answer, string $base = ''): ResponseInterface
    {
        $token = $this->csrf($base . '/');
        return $this->json('POST', $base . '/p/' . $page->slug . '/unlock', ['answer' => $answer], ['X-CSRF-TOKEN' => $token]);
    }

    public function testADraftIsNotPublic(): void
    {
        $page = $this->makePage($this->owner, ['plan' => 'free']);
        $this->assertSame(404, $this->get('/p/' . $page->slug)->getStatusCode());
        $this->assertSame(404, $this->get('/p/unknownslug')->getStatusCode());
    }

    public function testALockedPageSendsNoPhotoAndNoLetterUntilTheRightAnswer(): void
    {
        $page = $this->lockedPage();
        $response = $this->get('/p/' . $page->slug);
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('What is your endearment?', $html);
        $this->assertStringNotContainsString('secret-photo', $html);
        $this->assertStringNotContainsString('A secret paragraph', $html);
        $this->assertStringContainsString('"mode":"locked"', $html);
        $this->assertStringNotContainsString('const PASSWORD', (string) file_get_contents(base_path('public/assets/site.js')), 'no answer in the page script');
    }

    public function testTheRightAnswerOpensItWhateverTheCaseOrSpaces(): void
    {
        $page = $this->lockedPage();

        $wrong = $this->unlock($page, 'honey');
        $this->assertSame(422, $wrong->getStatusCode());
        $this->assertStringNotContainsString('secret', (string) $wrong->getBody());

        $right = $this->unlock($page, '  YAM ');
        $this->assertSame(200, $right->getStatusCode());
        $this->assertSame('no-store', $right->getHeaderLine('Cache-Control'));
        $content = $this->decode($right);
        $this->assertSame([$this->photoUrl($page, 'secret-photo.jpg')], $content['photos']);
        $this->assertSame(['A secret paragraph only she should read.'], $content['letter']);
        $this->assertSame('Jinelin', $content['recipient']);
    }

    public function testGuessingIsRateLimited(): void
    {
        $page = $this->lockedPage();
        $token = $this->csrf('/p/' . $page->slug);
        $codes = [];
        for ($i = 0; $i < 11; $i++) {
            $codes[] = $this->json('POST', '/p/' . $page->slug . '/unlock', ['answer' => 'guess' . $i], ['X-CSRF-TOKEN' => $token])->getStatusCode();
        }
        $this->assertSame(422, $codes[9]);
        $this->assertSame(429, $codes[10]);
    }

    public function testAnotherSpellingOfThePathDoesNotResetTheLimit(): void
    {
        $page = $this->lockedPage();
        $token = $this->csrf('/');
        for ($i = 0; $i < 10; $i++) {
            $this->json('POST', '/p/' . $page->slug . '/unlock', ['answer' => 'guess' . $i], ['X-CSRF-TOKEN' => $token]);
        }
        foreach (['/unlock/', '/unlock//', '/%75nlock', '/unlock%2F'] as $variant) {
            $response = $this->json('POST', '/p/' . $page->slug . $variant, ['answer' => 'yam'], ['X-CSRF-TOKEN' => $token]);
            $this->assertSame(429, $response->getStatusCode(), $variant);
        }
    }

    public function testUnlockingNeedsTheCsrfToken(): void
    {
        $page = $this->lockedPage();
        $this->assertSame(419, $this->json('POST', '/p/' . $page->slug . '/unlock', ['answer' => 'yam'])->getStatusCode());
    }

    public function testAnOpenPageCarriesItsContentAndOpensWithOneTap(): void
    {
        $page = $this->makeLivePage($this->owner, ['song_id' => 'fu9yk7gCTbc', 'song_title' => 'Palagi', 'song_artist' => 'TJ Monterde']);
        $this->makePhoto($page, 'https://cdn.test/open.jpg');
        $html = (string) $this->get('/p/' . $page->slug)->getBody();

        $this->assertStringContainsString('"mode":"open"', $html);
        $this->assertStringContainsString(str_replace('/', '\/', $this->photoUrl($page, 'open.jpg')), $html);
        $this->assertStringContainsString('First paragraph.', $html);
        $this->assertStringContainsString('class="open-btn"', $html);
        $this->assertStringContainsString('"id":"fu9yk7gCTbc"', $html);
        $this->assertStringNotContainsString('id="pw"', $html, 'no question to answer');
    }

    public function testPagesAreHiddenFromSearchEnginesUnlessTheOwnerAllowsIt(): void
    {
        $private = $this->makeLivePage($this->owner);
        $response = $this->get('/p/' . $private->slug);
        $this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow"', (string) $response->getBody());
        $this->assertSame("User-agent: *\nDisallow: /\n", (string) $this->get('/p/' . $private->slug . '/robots.txt')->getBody());
        $this->assertSame(404, $this->get('/p/' . $private->slug . '/sitemap.xml')->getStatusCode());

        $public = $this->makeLivePage($this->owner, ['searchable' => true]);
        $this->assertSame('index, follow', $this->get('/p/' . $public->slug)->getHeaderLine('X-Robots-Tag'));
        $this->assertStringContainsString('<loc>https://love.test/p/' . $public->slug . '</loc>', (string) $this->get('/p/' . $public->slug . '/sitemap.xml')->getBody());
        $this->assertStringContainsString(
            'Sitemap: https://love.test/p/' . $public->slug . "/sitemap.xml\n",
            (string) $this->get('/p/' . $public->slug . '/robots.txt')->getBody(),
            'a /p/ page points at its own sitemap route',
        );
    }

    public function testAnExpiredPageRestsWithoutShowingItsContent(): void
    {
        $page = $this->lockedPage(['plan' => 'sweet', 'expires_at' => '2026-01-01 00:00:00']);
        $response = $this->get('/p/' . $page->slug);
        $html = (string) $response->getBody();

        $this->assertSame(410, $response->getStatusCode());
        $this->assertStringContainsString('This page is resting for now.', $html);
        $this->assertStringNotContainsString('secret', $html);
        $this->assertSame(404, $this->unlock($page, 'yam')->getStatusCode(), 'no content after expiry, even with the answer');
    }

    public function testThePageIsRenderedInItsLayoutAndTheme(): void
    {
        $page = $this->makeLivePage($this->owner, ['layout' => 'polaroid', 'theme' => 'sunset']);
        $html = (string) $this->get('/p/' . $page->slug)->getBody();
        $this->assertStringContainsString('data-layout="polaroid"', $html);
        $this->assertStringContainsString('data-theme="sunset"', $html);
    }

    /** @return array<string,array{string}> */
    public static function templates(): array
    {
        return array_combine(array_keys(Designs::LAYOUTS), array_map(static fn (string $key): array => [$key], array_keys(Designs::LAYOUTS)));
    }

    /** Every template loads its own CSS and script, has the music controls, and gives nothing away before the answer. */
    #[DataProvider('templates')]
    public function testEveryTemplateIsServedAsItsOwnModule(string $layout): void
    {
        $page = $this->lockedPage(['layout' => $layout, 'theme' => 'blush', 'recipient' => '<b>Yam</b>']);
        $response = $this->get('/p/' . $page->slug);
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-layout="' . $layout . '"', $html);
        $own = Designs::LAYOUTS[$layout]['theme'] ?? null;
        $this->assertStringContainsString($own === null ? 'data-theme="blush"' : 'data-theme="' . $own . '"', $html, $own === null ? 'the page\'s theme' : 'a seasonal template wears its own colours');
        $this->assertStringContainsString($own === null ? 'data-scheme="light"' : 'data-scheme="dark"', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/assets/themes.css?v=', $html);
        $this->assertStringContainsString('<link rel="stylesheet" href="/assets/templates/' . $layout . '.css?v=', $html);
        $script = strpos($html, '<script src="/assets/templates/' . $layout . '.js?v=');
        $this->assertNotFalse($script, 'the template script');
        $this->assertGreaterThan($script, strpos($html, '<script src="/assets/site.js?v='), 'registered before site.js runs');
        $this->assertStringContainsString('"layout":"' . $layout . '"', $html);
        foreach (['id="show"', 'id="mute"', 'id="nowplaying"', 'id="npTitle"', 'id="announce"'] as $needed) {
            $this->assertStringContainsString($needed, $html, "{$needed}: site.js relies on it");
        }
        $this->assertStringNotContainsString('secret-photo', $html);
        $this->assertStringNotContainsString('A secret paragraph', $html);
        $this->assertStringNotContainsString('<b>Yam</b>', $html, 'the name is escaped in the template markup too');
        $this->assertStringContainsString('&lt;b&gt;Yam&lt;/b&gt;', $html);
    }

    public function testVisitorsSeeATemplateOfThePagesPlan(): void
    {
        $page = $this->makeLivePage($this->owner, ['plan' => 'sweet', 'expires_at' => '2099-01-01 00:00:00', 'layout' => 'stars']);
        $html = (string) $this->get('/p/' . $page->slug)->getBody();
        $this->assertStringContainsString('data-layout="classic"', $html, 'Sweet does not include the Starry sky (set by an admin, say)');
        $this->assertStringContainsString('/assets/templates/classic.js', $html);

        $page->forceFill(['plan' => 'sweetheart'])->save();
        $this->assertStringContainsString('data-layout="stars"', (string) $this->get('/p/' . $page->slug)->getBody());
    }

    public function testUserTextIsEscaped(): void
    {
        $page = $this->makeLivePage($this->owner, [
            'recipient' => '<script>alert(1)</script>',
            'gate_question' => '"><img src=x onerror=alert(2)>',
            'letter' => ['</script><script>alert(3)</script>'],
        ]);
        $html = (string) $this->get('/p/' . $page->slug)->getBody();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<script>alert(3)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
