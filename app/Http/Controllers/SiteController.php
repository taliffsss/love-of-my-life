<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveSiteHost;
use App\Models\Page;
use App\Models\Photo;
use App\Services\Designs;
use App\Services\Sites;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Security\Hasher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The page a visitor opens: /p/{slug}, <name>.<saas domain> or a custom domain.
 *
 * When the page has a lock screen, its HTML carries no photo, no letter and no photo URL: they are sent only in
 * answer to the right answer (POST …/unlock). Pages are kept out of search engines unless the owner allows it.
 */
final class SiteController
{
    public function __construct(private readonly Sites $sites)
    {
    }

    public function show(string $slug, ServerRequestInterface $request): ResponseInterface
    {
        $page = $this->publicPage($slug);
        if ($page->status() === Page::EXPIRED) {
            return $this->robots($page, view('site.expired', [
                'page' => $page,
                'brand' => (string) config('saas.brand'),
                'productUrl' => rtrim((string) config('app.url'), '/') . '/',
            ], 410));
        }
        $onOwnHost = $request->getAttribute(ResolveSiteHost::ATTRIBUTE) !== null;
        return $this->render($page, $page->hasGate() ? 'locked' : 'open', $onOwnHost ? '/unlock' : '/p/' . $page->slug . '/unlock');
    }

    public function unlock(string $slug, ServerRequestInterface $request, Hasher $hasher): ResponseInterface
    {
        $page = $this->publicPage($slug);
        if ($page->status() !== Page::ACTIVE) {
            throw new HttpException(404, 'Not Found');
        }
        if ($page->hasGate()) {
            $answer = self::normaliseAnswer((string) (Request::body($request)['answer'] ?? ''));
            if ($answer === '' || !$hasher->check($answer, (string) $page->gate_password)) {
                return Response::json(['message' => 'Not quite. Try again.'], 422)
                    ->withHeader('Cache-Control', 'no-store')
                    ->withHeader('X-Robots-Tag', 'noindex, nofollow');
            }
            if ($hasher->needsRehash((string) $page->gate_password)) {
                $page->forceFill(['gate_password' => $hasher->make($answer)])->save();
            }
        }
        return Response::json($this->content($page))
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function robotsTxt(string $slug): ResponseInterface
    {
        $page = Page::query()->where('slug', $slug)->first();
        $open = $page instanceof Page && $page->searchable && $page->isLive();
        $body = $open
            ? "User-agent: *\nAllow: /\nSitemap: " . rtrim($this->sites->url($page), '/') . "/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";
        return Response::html($body)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function sitemap(string $slug): ResponseInterface
    {
        $page = Page::query()->where('slug', $slug)->first();
        if (!$page instanceof Page || !$page->searchable || !$page->isLive()) {
            throw new HttpException(404, 'Not Found');
        }
        $loc = htmlspecialchars($this->sites->url($page), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $modified = ($page->updated_at ?? new \DateTimeImmutable())->format('Y-m-d');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>' . $loc . '</loc><lastmod>' . $modified
            . '</lastmod></url></urlset>' . "\n";
        return Response::html($xml)->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * @param string $mode open (content in the page) | locked (content after the answer) | preview (owner, content in the
     *                     page, any answer opens it) | demo (like preview, with the content given)
     * @param array{recipient:string,letter:list<string>,photos:list<string>}|null $content
     */
    public function render(Page $page, string $mode, string $unlockUrl = '', ?array $content = null): ResponseInterface
    {
        $content = $mode === 'locked' ? null : ($content ?? $this->content($page));
        $url = $this->sites->url($page);
        $layout = Designs::layout($page->layout);
        $theme = Designs::theme($page->theme);
        $assets = Designs::assets($layout);
        $assetVersion = (string) max(array_map(
            static fn (string $file): int => (int) @filemtime(base_path('public/' . $file)),
            ['assets/site.js', 'assets/site.css', 'assets/themes.css', $assets['css'], $assets['js']],
        ));

        $response = view('site.page', [
            'page' => $page,
            'mode' => $mode,
            'layout' => $layout,
            'theme' => $theme,
            'scheme' => Designs::THEMES[$theme]['scheme'],
            'themeColor' => Designs::THEMES[$theme]['colors'][0],
            'view' => Designs::view($layout),
            'assets' => $assets,
            'config' => [
                'mode' => $mode,
                'layout' => $layout,
                'unlockUrl' => $unlockUrl,
                'song' => $page->song_id !== null && $page->song_id !== ''
                    ? ['id' => $page->song_id, 'title' => (string) $page->song_title, 'artist' => (string) $page->song_artist]
                    : null,
                'content' => $content,
            ],
            'title' => 'For ' . $page->recipient,
            'url' => $url,
            'indexable' => $mode === 'open' || $mode === 'locked' ? $page->searchable : false,
            'assetVersion' => $assetVersion,
            'brand' => (string) config('saas.brand'),
            'productUrl' => rtrim((string) config('app.url'), '/') . '/',
        ]);

        return $this->robots($page, $response, $mode)
            // the open and preview pages carry the content; the locked shell is the same for everyone
            ->withHeader('Cache-Control', $content !== null ? 'private, no-store' : 'no-cache');
    }

    /** What the page shows once it is open. @return array{recipient:string,letter:list<string>,photos:list<string>} */
    public function content(Page $page): array
    {
        $photos = Photo::query()->where('page_id', $page->id)->orderBy('position')->orderBy('id')->pluck('url')->all();
        return [
            'recipient' => $page->recipient,
            'letter' => $page->paragraphs(),
            'photos' => array_values(array_map('strval', $photos)),
        ];
    }

    /** Answers are compared like the original page did: case, surrounding spaces and repeated spaces don't matter. */
    public static function normaliseAnswer(string $answer): string
    {
        $answer = trim($answer, " \t\r\n\"'");
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', $answer));
    }

    /** Only paid pages are public; a draft or unknown page is a plain 404 (it does not reveal that the page exists). */
    private function publicPage(string $slug): Page
    {
        $page = Page::query()->where('slug', $slug)->first();
        if (!$page instanceof Page || $page->status() === Page::DRAFT) {
            throw new HttpException(404, 'Not Found');
        }
        return $page;
    }

    private function robots(Page $page, ResponseInterface $response, string $mode = 'open'): ResponseInterface
    {
        $index = $page->searchable && in_array($mode, ['open', 'locked'], true) && $page->isLive();
        return $response->withHeader('X-Robots-Tag', $index ? 'index, follow' : 'noindex, nofollow');
    }
}
