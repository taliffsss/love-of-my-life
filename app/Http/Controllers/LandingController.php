<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Screen;
use App\Models\Page;
use App\Services\Designs;
use App\Services\PhotoAllowance;
use App\Services\PhotoStorage;
use App\Services\Plans;
use App\Services\Seasons;
use App\Services\Sites;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** The product's front door: what it is, the layouts, the prices, and a demo page anyone can open. */
final class LandingController
{
    public const DEMO_LETTER = [
        'If you are reading this, you found the little world I built for you. Every photo here is a day I would live again.',
        'Thank you for the slow mornings, the long drives and the way you laugh at my jokes even when they are not funny.',
        'I do not need anything grand. Just you, and more days like these. Happy monthsary, my love.',
    ];

    /** The sample invitation behind /demo?occasion=invitation: made-up hosts, so nobody's real details are shown. */
    public const DEMO_INVITATION = [
        'Together with our families, we joyfully invite you to celebrate the wedding of Ana and Miguel. It would mean the world to have you with us.',
        'Saturday, 12 December 2026, at 3:00 PM, at San Agustin Church, Manila. The reception follows at the Garden Hall.',
        'Kindly let us know by 1 December. Dress code: pastel formal. We cannot wait to celebrate with you.',
    ];

    public function index(ServerRequestInterface $request, Plans $plans, Sites $sites, PhotoAllowance $allowance, Seasons $seasons): ResponseInterface
    {
        // The look follows the season that is on (App\Services\Seasons::theme). Outside production (or with THEME_PREVIEW=true), ?theme=halloween|christmas|valentine|default previews any
        // of them as if its season were on: its template and its hero chip appear, and the others' do not.
        $theme = $seasons->look($request->getQueryParams());
        $previewing = $seasons->canPreview() && $theme === ($request->getQueryParams()['theme'] ?? null);
        $on = $previewing ? static fn (string $k): bool => $k === $theme : fn (string $k): bool => $seasons->isOpen($k);
        $offered = array_filter(Designs::LAYOUTS, static fn (array $l, string $k): bool => empty($l['season']) || $on($k), ARRAY_FILTER_USE_BOTH);
        $paid = $plans->paid();
        $freePhotos = $allowance->freePhotos();
        $pack = $allowance->packOptions()[1];
        $maxPhoto = PhotoStorage::megabytes((int) config('saas.max_upload_bytes'));
        $faq = $this->faq($paid, $freePhotos, $pack, $maxPhoto, $sites->domain());
        return Screen::render('landing', [
            'theme' => $theme,
            'faq' => $faq,
            'occasions' => $this->occasions(),
            'openSeasons' => array_values(array_filter(array_keys($offered), static fn (string $k): bool => !empty(Designs::LAYOUTS[$k]['season']))),
            'plans' => $paid,
            'freePhotos' => $freePhotos,
            'pack' => $pack,
            'maxPhoto' => $maxPhoto,
            'structuredData' => $this->structuredData($faq, $paid),
            'layouts' => $offered,
            'themes' => Designs::pickableThemes(),
            'domain' => $sites->domain(),
            'siteUrl' => rtrim((string) config('app.url'), '/') . '/',
        ]);
    }

    /** /invitations — the same product for weddings, debuts, christenings and other parties: what it does, the templates, the plans. */
    public function invitations(Plans $plans, Seasons $seasons, Sites $sites): ResponseInterface
    {
        $templates = [];
        foreach (Designs::OCCASION_DESIGNS['invitation'] as $key => [$name, $blurb]) {
            if (!empty(Designs::LAYOUTS[$key]['season']) && !$seasons->isOpen($key)) {
                continue; // a seasonal party template is only offered in its window
            }
            $first = $plans->plansWithTemplate($key)[0] ?? null;
            $templates[] = ['key' => $key, 'name' => $name, 'blurb' => $blurb, 'plan' => $first === null ? null : (string) $plans->get($first)['name'], 'seasonal' => !empty(Designs::LAYOUTS[$key]['season'])];
        }
        $siteUrl = rtrim((string) config('app.url'), '/') . '/';
        return Screen::render('invitations', [
            'theme' => 'default',
            'templates' => $templates,
            'plans' => $plans->paid(),
            'siteUrl' => $siteUrl,
            'pageUrl' => $siteUrl . 'invitations',
            'structuredData' => $this->invitationsStructuredData($siteUrl),
            'freePhotos' => app(PhotoAllowance::class)->freePhotos(),
            'domain' => $sites->domain(),
        ]);
    }

    /**
     * The occasions section of the landing page: what each one is called when people search for it, and one honest line about it.
     * The names come from Designs::OCCASIONS, so a renamed occasion shows up here too; only the invitations have a page of their own.
     *
     * @return list<array{key:string,eyebrow:string,title:string,text:string,href:?string}>
     */
    private function occasions(): array
    {
        $copy = [
            'birthday' => ['Birthday surprise', 'A surprise box that opens on your photos and your birthday wishes.'],
            'anniversary' => ['Anniversary love letter', 'Another year together on one private page: your photos, your song and your words, opened with a question only they can answer.'],
            'partner' => ['Boyfriend, girlfriend and monthsary', 'A monthsary message, a sweet surprise, or a scratch card that asks "Will you be mine?". Just because counts too.'],
            'wedding' => ['Wedding vows and story', 'Tell how it happened, page by page, in a little storybook to share with everyone who loves you.'],
            'invitation' => ['Online invitations', 'Weddings, debuts, christenings and baby showers: share a link or print the QR code on a card.'],
        ];
        $out = [];
        foreach ($copy as $key => [$title, $text]) {
            $out[] = ['key' => $key, 'eyebrow' => (string) (Designs::OCCASIONS[$key]['name'] ?? $key), 'title' => $title, 'text' => $text, 'href' => $key === 'invitation' ? '/invitations' : null];
        }
        return $out;
    }

    /**
     * The questions of the landing page's "Good to know" section, as [question, answer] in plain text: the page prints them and
     * schema.org's FAQPage repeats them, so both always say the same.
     *
     * @param array<string,array<string,mixed>> $plans
     * @param array{photos:int,price:int} $pack
     * @return list<array{0:string,1:string}>
     */
    private function faq(array $plans, int $freePhotos, array $pack, string $maxPhoto, string $domain): array
    {
        $names = array_values($plans);
        $perPlan = '';
        foreach ($names as $i => $p) {
            $perPlan .= ($i > 0 ? ($i === count($names) - 1 ? ' and ' : ', ') : '') . $p['name'] . ' ' . $p['photos'];
        }
        $faq = [
            ['Who can see my page?', 'Only people with the link, and if you set a question, only someone who knows the answer. Your photos and letter are not even sent to the browser until the right answer is typed. Pages are hidden from search engines.'],
            ['Can I change it after I send it?', 'Yes. Change the letter, the photos, the song, the question, the template or the colours whenever you like. The link and the QR code stay the same.'],
        ];
        if (config('saas.features.subdomains')) {
            $faq[] = ['What is the dedicated address?', "With the Sweetheart and Forever plans your page can live at its own address, like yourname.{$domain}. Pick the name in your dashboard and it works right away."];
        }
        $faq[] = ['How many photos can I add?', "Photos belong to your account and are shared by all your pages. The free preview includes {$freePhotos}; each live page adds the photos of its plan: {$perPlan}. Every photo counts until you delete it. When you run out, add {$pack['photos']} more for " . Plans::money($pack['price']) . "; they never expire. Each photo can be up to {$maxPhoto} and fits every template without being stretched."];
        $faq[] = ['What happens when a monthly or yearly plan ends?', 'The page rests: visitors see a short note instead of your content, and nothing is deleted. Renew from your dashboard, for a month or a year, and it is back immediately, with the same link. Renewing early adds the time to what is left.'];
        $faq[] = ['How do I pay?', 'Pick a plan in your dashboard. You will see the amount and an order reference to pay by GCash or Maya.'];
        return $faq;
    }

    /**
     * Who the site is, for search engines (schema.org, JSON-LD): the organisation with its logo, the site, the product with its
     * prices (read from the plans, so they cannot drift from the page) and the questions of the FAQ.
     *
     * @param list<array{0:string,1:string}> $faq
     * @param array<string,array<string,mixed>> $plans
     */
    private function structuredData(array $faq, array $plans): array
    {
        $site = rtrim((string) config('app.url'), '/') . '/';
        $brand = (string) config('saas.brand');
        $summary = 'Make a private love page: your photos, your song and a letter, opened with a question only they can answer.';
        $per = ['month' => 'per month', 'year' => 'per year', 'once' => 'one time'];
        $offers = [];
        foreach ($plans as $plan) {
            foreach ((array) ($plan['prices'] ?? []) as $period => $centavos) {
                $offers[] = ['@type' => 'Offer', 'name' => $plan['name'] . ' plan, ' . ($per[$period] ?? $period), 'price' => number_format($centavos / 100, 2, '.', ''), 'priceCurrency' => 'PHP', 'url' => $site . '#pricing'];
            }
        }
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $site . '#organization',
                    'name' => $brand,
                    'url' => $site,
                    // a square logo at least 112 px, as Google asks for; the file is public/icons/icon-512.png
                    'logo' => ['@type' => 'ImageObject', 'url' => $site . 'icons/icon-512.png', 'width' => 512, 'height' => 512],
                    'image' => $site . 'og-image.png',
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $site . '#website',
                    'url' => $site,
                    'name' => $brand,
                    'description' => $summary,
                    'inLanguage' => 'en',
                    'publisher' => ['@id' => $site . '#organization'],
                ],
                [
                    '@type' => 'WebApplication',
                    '@id' => $site . '#app',
                    'name' => $brand,
                    'url' => $site,
                    'description' => 'Digital love letters and online invitations for birthdays, weddings, anniversaries and couples: photos, a song and a letter on one private page, shared as a link or a QR code.',
                    'applicationCategory' => 'LifestyleApplication',
                    'operatingSystem' => 'Any (web browser)',
                    'inLanguage' => 'en',
                    'publisher' => ['@id' => $site . '#organization'],
                    'offers' => $offers,
                ],
                [
                    '@type' => 'FAQPage',
                    '@id' => $site . '#faq',
                    'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $faq),
                ],
            ],
        ];
    }

    private function invitationsStructuredData(string $site): array
    {
        $page = $site . 'invitations';
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $page . '#webpage',
                    'url' => $page,
                    'name' => 'Online wedding, debut, christening invitations',
                    'description' => 'Make your wedding, debut or christening invitation online: pick a template, add photos and your message, then share the link or print the QR code on a card.',
                    'inLanguage' => 'en',
                    'isPartOf' => ['@id' => $site . '#website'],
                    'primaryImageOfPage' => $site . 'assets/invitations/og-invitations.jpg',
                    'breadcrumb' => ['@id' => $page . '#breadcrumb'],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $page . '#breadcrumb',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => (string) config('saas.brand'), 'item' => $site],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Invitations', 'item' => $page],
                    ],
                ],
            ],
        ];
    }

    /** /demo?layout=cinematic&theme=midnight — the real page, with sample content; any answer opens it. */
    public function demo(ServerRequestInterface $request, SiteController $site): ResponseInterface
    {
        $query = $request->getQueryParams();
        // ?occasion=invitation shows the template as an invitation: addressed to the guests, no question, made-up hosts
        $invitation = ($query['occasion'] ?? null) === 'invitation';
        $recipient = $invitation ? 'Our family and friends' : 'Alex';
        $letter = $invitation ? self::DEMO_INVITATION : self::DEMO_LETTER;
        $page = new Page();
        $page->forceFill([
            'slug' => 'demo',
            'recipient' => $recipient,
            'letter' => $letter,
            'occasion' => $invitation ? 'invitation' : null,
            'song_id' => null,
            'gate_enabled' => !$invitation,
            'gate_eyebrow' => Page::DEFAULT_EYEBROW,
            'gate_question' => 'What do I always call you?',
            'layout' => Designs::layout(is_string($query['layout'] ?? null) ? $query['layout'] : null),
            'theme' => Designs::theme(is_string($query['theme'] ?? null) ? $query['theme'] : null),
            'plan' => 'forever',
            'searchable' => false,
        ]);
        return $site->render($page, 'demo', '', [
            'recipient' => $recipient,
            'occasion' => $page->occasion,
            'letter' => $letter,
            'photos' => ['/assets/demo/1.svg', '/assets/demo/2.svg', '/assets/demo/3.svg', '/assets/demo/4.svg'],
        ]);
    }
}
