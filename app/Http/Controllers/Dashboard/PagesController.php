<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\SiteController;
use App\Http\Screen;
use App\Models\Order;
use App\Models\Page;
use App\Models\Photo;
use App\Services\Designs;
use App\Services\LetterStore;
use App\Services\PhotoAllowance;
use App\Services\PhotoStorage;
use App\Services\Plans;
use App\Services\QrCode;
use App\Services\Sites;
use App\Services\Subdomains;
use App\Services\YouTube;
use Naluz\Auth\Auth;
use Naluz\Database\Connection;
use Naluz\Http\HttpException;
use Naluz\Http\Request;
use Naluz\Http\Response;
use Naluz\Security\Hasher;
use Naluz\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** The owner's side of a page: list, create, edit every part, preview, share (QR) and delete. */
final class PagesController
{
    use OwnsPages;

    /** How many unpaid drafts one account may keep, so free pages cannot be used as free photo storage. */
    public const MAX_DRAFTS = 5;

    public const STARTER_LETTER = [
        'Every day with you is my favourite day. I made this little corner of the internet just for you, so you can come back to it whenever you miss me.',
        'Thank you for the laughter, the patience and the quiet moments. Thank you for choosing me, again and again.',
        'Whatever comes next, I want to face it with you. I love you, today and every day after.',
    ];

    public function index(Auth $auth, Sites $sites, PhotoAllowance $allowance): ResponseInterface
    {
        $pages = Page::query()->where('user_id', (int) $auth->id())->orderBy('id', 'desc')->get()->all();
        $covers = [];
        $counts = [];
        if ($pages !== []) {
            $photos = Photo::query()->whereIn('page_id', array_map(static fn (Page $p): int => $p->id, $pages))
                ->orderBy('position')->orderBy('id')->get()->all();
            foreach ($photos as $photo) {
                $covers[$photo->page_id] ??= $photo->url;
                $counts[$photo->page_id] = ($counts[$photo->page_id] ?? 0) + 1;
            }
        }

        return Screen::render('dashboard.index', [
            'pages' => $pages,
            'usage' => $allowance->of((int) $auth->id()),
            'packs' => $allowance->packOptions(),
            'covers' => $covers,
            'counts' => $counts,
            'urls' => array_combine(
                array_map(static fn (Page $p): int => $p->id, $pages),
                array_map(static fn (Page $p): string => $sites->url($p), $pages),
            ) ?: [],
        ]);
    }

    public function store(ServerRequestInterface $request, Auth $auth): ResponseInterface
    {
        try {
            $recipient = LetterStore::parseName((string) (Request::body($request)['recipient'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(['recipient' => [$e->getMessage()]], Request::body($request));
        }

        $drafts = Page::query()->where('user_id', (int) $auth->id())->where('plan', 'free')->count();
        if ($drafts >= self::MAX_DRAFTS) {
            throw new ValidationException(
                ['recipient' => ['You already have ' . self::MAX_DRAFTS . ' unpublished drafts. Publish or delete one first.']],
                Request::body($request),
            );
        }

        $page = new Page();
        $page->forceFill([
            'user_id' => (int) $auth->id(),
            'slug' => Page::newSlug(),
            'recipient' => $recipient,
            'letter' => self::STARTER_LETTER,
            'gate_enabled' => false,
            'gate_eyebrow' => Page::DEFAULT_EYEBROW,
            'gate_question' => Page::DEFAULT_QUESTION,
            'layout' => Designs::DEFAULT_LAYOUT,
            'theme' => Designs::DEFAULT_THEME,
            'plan' => 'free',
            'searchable' => false,
        ])->save();

        return Screen::back($this->editUrl($page), 'Your page for ' . $recipient . ' is ready to fill in.');
    }

    public function edit(string $slug, Auth $auth, Sites $sites, Plans $plans, Subdomains $subdomains, PhotoStorage $storage, PhotoAllowance $allowance): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $photos = Photo::query()->where('page_id', $page->id)->orderBy('position')->orderBy('id')->get()->all();
        $orders = Order::query()->where('page_id', $page->id)->where('user_id', (int) $auth->id())
            ->orderBy('id', 'desc')->limit(5)->get()->all();

        return Screen::render('dashboard.edit', [
            'page' => $page,
            'photos' => $photos,
            'orders' => $orders,
            'url' => $sites->url($page),
            'pathUrl' => $sites->pathUrl($page),
            'domain' => $subdomains->domain(),
            'plans' => $plans->all(),
            'purchasable' => $plans->purchasable($page),
            'layouts' => Designs::LAYOUTS,
            'templateNotes' => $this->templateNotes($page, $plans),
            'themes' => Designs::THEMES,
            'maxUploadBytes' => $storage->maxBytes(),
            'storageDisk' => $storage->diskName(),
            'usage' => $allowance->of((int) $auth->id()),
            'packs' => $allowance->packOptions(),
        ]);
    }

    /**
     * For the design picker: per template, whether this page may use it and, when not every plan includes it, which do.
     *
     * @return array<string,array{locked:bool,plans:string}>
     */
    private function templateNotes(Page $page, Plans $plans): array
    {
        $paid = count($plans->paid());
        $notes = [];
        foreach (array_keys(Designs::LAYOUTS) as $key) {
            $with = $plans->plansWithTemplate($key);
            $notes[$key] = [
                'locked' => $page->status() !== Page::DRAFT && !$plans->allowsTemplate($page->plan, $key),
                'plans' => count($with) < $paid ? $plans->names($with) : '',
            ];
        }
        return $notes;
    }

    public function updateMessage(string $slug, ServerRequestInterface $request, Auth $auth): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $body = Request::body($request);
        $errors = [];
        try {
            $recipient = LetterStore::parseName((string) ($body['recipient'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            $errors['recipient'] = [$e->getMessage()];
        }
        try {
            $letter = LetterStore::parse((string) ($body['message'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            $errors['message'] = [$e->getMessage()];
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $body);
        }

        $page->forceFill(['recipient' => $recipient, 'letter' => $letter])->save();
        return Screen::back($this->editUrl($page, 'message'), 'Letter saved.');
    }

    public function updateSong(string $slug, ServerRequestInterface $request, Auth $auth, YouTube $youtube): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $body = Request::body($request);

        if (($body['remove'] ?? '') === '1') {
            $page->forceFill(['song_id' => null, 'song_title' => null, 'song_artist' => null])->save();
            return Screen::back($this->editUrl($page, 'song'), 'The song was removed.');
        }

        $id = YouTube::parseId((string) ($body['url'] ?? ''));
        if ($id === null) {
            throw new ValidationException(['url' => ['Paste a YouTube link, for example https://www.youtube.com/watch?v=fu9yk7gCTbc']], $body);
        }
        try {
            $info = $youtube->lookup($id);
        } catch (\InvalidArgumentException $e) {
            throw new ValidationException(['url' => [$e->getMessage()]], $body);
        }

        $page->forceFill(['song_id' => $id, 'song_title' => mb_substr($info['title'], 0, 200), 'song_artist' => mb_substr($info['artist'], 0, 200)])->save();
        return Screen::back($this->editUrl($page, 'song'), 'Song saved: ' . $info['title'] . '.');
    }

    public function updateGate(string $slug, ServerRequestInterface $request, Auth $auth, Hasher $hasher): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $body = Request::body($request);
        $enabled = in_array($body['gate_enabled'] ?? '', ['1', 'on', 'true'], true);
        $eyebrow = trim((string) ($body['gate_eyebrow'] ?? ''));
        $question = trim((string) ($body['gate_question'] ?? ''));
        // named *password* so the framework never flashes it back into the session with the old input
        $answer = SiteController::normaliseAnswer((string) ($body['gate_password'] ?? ''));

        $errors = [];
        if (mb_strlen($eyebrow) > 60) {
            $errors['gate_eyebrow'] = ['Keep the small heading under 60 characters.'];
        }
        if ($question === '' || mb_strlen($question) > 120) {
            $errors['gate_question'] = ['Write the question (at most 120 characters).'];
        }
        if (mb_strlen($answer) > 100) {
            $errors['gate_password'] = ['Keep the answer under 100 characters.'];
        }
        if ($enabled && $answer === '' && !$page->hasGate()) {
            $errors['gate_password'] = ['Choose the answer that opens the page.'];
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $body);
        }

        $page->forceFill([
            'gate_enabled' => $enabled,
            'gate_eyebrow' => $eyebrow !== '' ? $eyebrow : Page::DEFAULT_EYEBROW,
            'gate_question' => $question,
        ]);
        if ($answer !== '') {
            $page->forceFill(['gate_password' => $hasher->make($answer)]);
        }
        $page->save();

        return Screen::back($this->editUrl($page, 'lock'), $enabled ? 'The lock screen is on.' : 'The lock screen is off: the page opens with one tap.');
    }

    public function updateDesign(string $slug, ServerRequestInterface $request, Auth $auth, Plans $plans): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $body = Request::body($request);
        $layout = (string) ($body['layout'] ?? '');
        $theme = (string) ($body['theme'] ?? '');
        $errors = [];
        if (!isset(Designs::LAYOUTS[$layout])) {
            $errors['layout'] = ['Pick one of the templates.'];
        } elseif ($page->status() !== Page::DRAFT && !$plans->allowsTemplate($page->plan, $layout)) {
            // a draft may try any template; a published page only those of its plan
            $errors['layout'] = [sprintf(
                'The %s template comes with %s. Upgrade the plan to use it on this page.',
                Designs::LAYOUTS[$layout]['name'],
                $plans->names($plans->plansWithTemplate($layout)),
            )];
        }
        if (!isset(Designs::THEMES[$theme])) {
            $errors['theme'] = ['Pick one of the colour themes.'];
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $body);
        }

        $page->forceFill(['layout' => $layout, 'theme' => $theme])->save();
        return Screen::back(
            $this->editUrl($page, 'design'),
            'Design saved: the ' . Designs::LAYOUTS[$layout]['name'] . ' template in ' . Designs::THEMES[$theme]['name'] . ' colours.',
        );
    }

    public function preview(string $slug, ServerRequestInterface $request, Auth $auth, SiteController $site): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        $query = $request->getQueryParams();
        // the design picker previews a choice before it is saved
        $page->setAttribute('layout', Designs::layout(is_string($query['layout'] ?? null) ? $query['layout'] : $page->layout));
        $page->setAttribute('theme', Designs::theme(is_string($query['theme'] ?? null) ? $query['theme'] : $page->theme));
        return $site->render($page, 'preview');
    }

    public function qr(string $slug, ServerRequestInterface $request, Auth $auth, Sites $sites): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        // Printed cards outlive addresses: the QR code opens the permanent /p/ link, which never changes and can never be
        // claimed by someone else (a released subdomain can).
        $response = Response::html(QrCode::svg($sites->pathUrl($page)))
            ->withHeader('Content-Type', 'image/svg+xml; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
        if (($request->getQueryParams()['download'] ?? '') === '1') {
            $name = 'for-' . (preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($page->shortName())) ?: 'you') . '-qr.svg';
            $response = $response->withHeader('Content-Disposition', 'attachment; filename="' . trim($name, '-') . '"');
        }
        return $response;
    }

    public function destroy(string $slug, ServerRequestInterface $request, Auth $auth, PhotoStorage $storage, Connection $db): ResponseInterface
    {
        $page = $this->ownedPage($auth, $slug);
        if ((Request::body($request)['confirm'] ?? '') !== 'delete') {
            throw new HttpException(422, 'Confirm that the page should be deleted.');
        }

        $photos = Photo::query()->withoutCache()->useWritePdo()->where('page_id', $page->id)->get()->all();
        $db->transaction(function () use ($db, $page, $photos, $storage): void {
            $db->table('photos')->where('page_id', '=', $page->id)->delete();
            $storage->trash(...$photos);
            // paid orders stay on record without their page; photo packs bought from it stay on the account
            $db->table('orders')->where('page_id', '=', $page->id)->update(['page_id' => null]);
            $db->table('pages')->where('id', '=', $page->id)->delete();
        });
        // the files go once the rows are gone: one that cannot be deleted now waits in the trash (bin/empty-trash.php),
        // and one another photo still uses is kept
        $storage->purge(...$photos);

        $count = count($photos);
        $freed = match (true) {
            $count === 0 => '.',
            $count === 1 => ', with its photo, which no longer counts towards your account.',
            default => sprintf(', with its %d photos, which no longer count towards your account.', $count),
        };
        return Screen::back('/dashboard', 'The page for ' . $page->recipient . ' was deleted' . $freed);
    }
}
