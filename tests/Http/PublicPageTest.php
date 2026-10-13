<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Models\Page;
use App\Models\User;
use Naluz\Security\Hasher;
use Naluz\Tests\TestCase;
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
        $this->assertSame(['https://cdn.test/secret-photo.jpg'], $content['photos']);
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
        $this->assertStringContainsString('https:\/\/cdn.test\/open.jpg', $html);
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
        $page = $this->lockedPage(['plan' => 'basic', 'expires_at' => '2026-01-01 00:00:00']);
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
