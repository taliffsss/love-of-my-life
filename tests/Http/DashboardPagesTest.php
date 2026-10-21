<?php

declare(strict_types=1);

namespace Naluz\Tests\Http;

use App\Http\Controllers\Dashboard\PagesController;
use App\Models\Order;
use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use App\Services\Designs;
use App\Services\OccasionTemplates;
use App\Services\YouTube;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Naluz\Security\Hasher;
use Naluz\Tests\TestCase;

final class DashboardPagesTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->makeUser();
        $this->signIn($this->owner);
    }

    private function youtubeAnswers(GuzzleResponse ...$responses): void
    {
        $this->app->instance(YouTube::class, new YouTube(new Client(['handler' => HandlerStack::create(new MockHandler($responses))])));
    }

    public function testCreatingAPageStartsAPrivateDraftWithAStarterLetter(): void
    {
        $response = $this->submit('POST', '/dashboard/pages', ['recipient' => '  Jinelin   Basela ']);

        $page = Page::query()->first();
        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('/dashboard/pages/' . $page->slug, $response->getHeaderLine('Location'));
        $this->assertSame('Jinelin Basela', $page->recipient);
        $this->assertSame($this->owner->id, $page->user_id);
        $this->assertSame(Page::DRAFT, $page->status());
        $this->assertSame(PagesController::STARTER_LETTER, $page->paragraphs());
        $this->assertMatchesRegularExpression('/^[a-z2-9]{10}$/', $page->slug);
        $this->assertFalse($page->hasGate());

        $html = (string) $this->get('/dashboard')->getBody();
        $this->assertStringContainsString('For Jinelin Basela', $html);
        $this->assertStringContainsString('Draft', $html);
    }

    public function testCreatingAPageFromAnOccasionTemplateFillsItIn(): void
    {
        $this->submit('POST', '/dashboard/pages', ['recipient' => 'Mia', 'template' => 'birthday-happy']);

        $page = Page::query()->first();
        $this->assertInstanceOf(Page::class, $page);
        $template = OccasionTemplates::find('birthday-happy');
        $this->assertSame($template['letter'], $page->paragraphs());
        $this->assertSame($template['layout'], $page->layout);
        $this->assertSame($template['theme'], $page->theme);
        $this->assertSame($template['eyebrow'], $page->gate_eyebrow);
        $this->assertSame($template['question'], $page->gate_question);
        $this->assertSame('birthday', $page->occasion);
        $this->assertSame(Page::DRAFT, $page->status());
    }

    public function testTheDesignCanBeGivenAnOccasionAndItDressesTheTemplate(): void
    {
        $page = $this->makePage($this->owner, ['layout' => 'classic', 'theme' => 'rose']);
        $url = '/dashboard/pages/' . $page->slug;
        $this->assertNull($page->occasion);
        $this->assertStringNotContainsString('data-occasion', (string) $this->get($url . '/preview')->getBody());

        $this->submit('PUT', $url . '/design', ['layout' => 'envelope', 'theme' => 'blush', 'occasion' => 'wedding']);
        $page = Page::query()->where('slug', $page->slug)->first();
        $this->assertSame('wedding', $page->occasion);
        $this->assertSame('envelope', $page->layout);

        $html = (string) $this->get($url . '/preview')->getBody();
        $this->assertStringContainsString('data-occasion="wedding"', $html);
        $this->assertStringContainsString('/assets/occasions.css', $html);

        // a request that says nothing of the occasion keeps it; an empty one removes it
        $this->submit('PUT', $url . '/design', ['layout' => 'gift', 'theme' => 'blush']);
        $this->assertSame('wedding', Page::query()->where('slug', $page->slug)->first()->occasion);
        $this->submit('PUT', $url . '/design', ['layout' => 'gift', 'theme' => 'blush', 'occasion' => '']);
        $this->assertNull(Page::query()->where('slug', $page->slug)->first()->occasion);
    }

    public function testAnUnknownOccasionIsRefused(): void
    {
        $page = $this->makePage($this->owner, ['layout' => 'classic', 'theme' => 'rose']);
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/design', ['layout' => 'gift', 'theme' => 'rose', 'occasion' => 'funeral']);
        $fresh = Page::query()->where('slug', $page->slug)->first();
        $this->assertSame('classic', $fresh->layout);
        $this->assertNull($fresh->occasion);
    }

    public function testThePreviewTriesAnOccasionBeforeItIsSaved(): void
    {
        $page = $this->makePage($this->owner, ['layout' => 'classic', 'theme' => 'rose']);
        $html = (string) $this->get('/dashboard/pages/' . $page->slug . '/preview?layout=stars&occasion=birthday')->getBody();
        $this->assertStringContainsString('data-occasion="birthday"', $html);
        $this->assertStringContainsString('data-layout="stars"', $html);
        $this->assertNull(Page::query()->where('slug', $page->slug)->first()->occasion);
    }

    public function testTheDesignPickerHasADesignPerTemplateForEveryOccasion(): void
    {
        $page = $this->makePage($this->owner);
        $html = (string) $this->get('/dashboard/pages/' . $page->slug)->getBody();
        $this->assertSame(6, substr_count($html, 'class="choice-grid design-grid"'), 'Plain and the five occasions');
        foreach (Designs::OCCASION_DESIGNS as $occasion => $designs) {
            $this->assertCount(13, $designs, $occasion);
            $this->assertSame(array_keys(Designs::LAYOUTS), array_keys($designs));
            foreach ($designs as [$name]) {
                $this->assertStringContainsString('<b>' . $name . '</b>', $html);
            }
        }
    }

    public function testAnUnknownOccasionTemplateCreatesNothing(): void
    {
        $this->submit('POST', '/dashboard/pages', ['recipient' => 'Mia', 'template' => 'not-a-template']);
        $this->assertSame(0, Page::query()->count());
    }

    public function testTheDashboardOffersTenTemplatesForEveryOccasion(): void
    {
        $html = (string) $this->get('/dashboard')->getBody();
        foreach (OccasionTemplates::OCCASIONS as $occasion) {
            $this->assertStringContainsString($occasion['name'], $html);
        }
        $this->assertSame(50, substr_count($html, 'name="template"'));
    }

    public function testEveryPageCardHasADeleteButtonAndConfirmation(): void
    {
        $a = $this->makePage($this->owner, ['recipient' => 'Ana']);
        $b = $this->makePage($this->owner, ['recipient' => 'Ben']);
        $html = (string) $this->get('/dashboard')->getBody();
        foreach ([$a, $b] as $page) {
            $this->assertStringContainsString('data-open="#delete-' . $page->slug . '"', $html);
            $this->assertStringContainsString('id="delete-' . $page->slug . '"', $html);
            $this->assertStringContainsString('action="/dashboard/pages/' . $page->slug . '"', $html);
        }
        $this->assertLessThan(strpos($html, 'data-open="#delete-' . $a->slug), strpos($html, '/preview"', strpos($html, $a->slug)) ?: PHP_INT_MAX);
    }

    public function testAPageNeedsSomeoneToBeFor(): void
    {
        $this->submit('POST', '/dashboard/pages', ['recipient' => '   ']);
        $this->assertSame(0, Page::query()->count());
    }

    public function testTheDashboardShowsTheAccountsPhotos(): void
    {
        $this->makePhoto($this->makePage($this->owner), 'https://cdn.test/a.jpg', 1);
        $this->makePhoto($this->makePage($this->owner), 'https://cdn.test/b.jpg', 1);
        $html = (string) $this->get('/dashboard')->getBody();
        $this->assertStringContainsString('Subscribe to a plan to add photos.', $html, 'no live plan: subscribe first');
        $this->assertStringNotContainsString('action="/dashboard/photo-packs"', $html);

        $this->makeLivePage($this->owner, ['plan' => 'sweet', 'expires_at' => '2099-01-01 00:00:00']);
        $html = (string) $this->get('/dashboard')->getBody();
        $this->assertStringNotContainsString('Subscribe to a plan to add photos.', $html);
        $this->assertMatchesRegularExpression('#<b id="usage-used">2</b> of <span id="usage-total">12</span> used · <span id="usage-left">10 left</span>#', $html);
        $this->assertStringContainsString('Need more photos? 12 for ₱100', $html);
        $this->assertStringContainsString('action="/dashboard/photo-packs"', $html);
        $this->assertStringNotContainsString('name="page"', $html, 'bought from the dashboard: no page to come back to');
    }

    public function testTheTourShowsOnceAndIsRemembered(): void
    {
        $html = (string) $this->get('/dashboard')->getBody();
        $this->assertStringContainsString('id="tour-steps"', $html, 'a new account is shown the tour');
        $this->assertStringContainsString('data-target="#recipient"', $html);

        $response = $this->ajax('POST', '/dashboard/tour');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull(User::query()->where('id', $this->owner->id)->first()->tour_seen_at);

        $html = (string) $this->get('/dashboard')->getBody();
        $this->assertStringNotContainsString('id="tour-steps"', $html, 'finished or skipped: never again');
        $this->assertSame(200, $this->ajax('POST', '/dashboard/tour')->getStatusCode(), 'asking twice is harmless');
    }

    public function testTheEditorHasItsOwnTourThatShowsOnce(): void
    {
        $page = $this->makePage($this->owner);
        $url = '/dashboard/pages/' . $page->slug;
        $this->assertStringContainsString('data-tour="editor"', (string) $this->get($url)->getBody());

        $this->ajax('POST', '/dashboard/tour', ['tour' => 'dashboard']);
        $this->assertStringContainsString('data-tour="editor"', (string) $this->get($url)->getBody(), 'finishing the dashboard tour does not finish the editor\'s');

        $this->ajax('POST', '/dashboard/tour', ['tour' => 'editor']);
        $this->assertStringNotContainsString('id="tour-steps"', (string) $this->get($url)->getBody());
    }

    public function testASignedInVisitorWhoPicksAPlanSeesItsTemplates(): void
    {
        $this->assertSame(50, substr_count((string) $this->get('/dashboard')->getBody(), 'name="template"'));
        $html = (string) $this->get('/dashboard?plan=sweet')->getBody();
        $this->assertSame('sweet', User::query()->where('id', $this->owner->id)->first()->intended_plan);
        $this->assertSame(25, substr_count($html, 'name="template"'));
    }

    public function testADraftEditorShowsOnlyThePlansTemplatesAndTheOnesTheyAskedFor(): void
    {
        $page = $this->makePage($this->owner, ['layout' => 'classic']);
        $url = '/dashboard/pages/' . $page->slug;
        $this->assertSame(13 * 6, substr_count((string) $this->get($url)->getBody(), 'name="layout"'), 'no plan chosen: all 13 in each of the 6 groups');

        $this->get('/dashboard?plan=sweet');
        $html = (string) $this->get($url)->getBody();
        $this->assertSame(5 * 6, substr_count($html, 'name="layout"'), 'Sweet: 5 templates');
        $this->assertStringContainsString('Showing the 5 templates that come with Sweet', $html);
        $this->assertStringNotContainsString('value="stars"', $html);
        $this->assertSame(13 * 6, substr_count((string) $this->get($url . '?templates=all')->getBody(), 'name="layout"'));
    }

    public function testFreeDraftsAreLimited(): void
    {
        for ($i = 0; $i < PagesController::MAX_DRAFTS; $i++) {
            $this->makePage($this->owner);
        }
        $this->submit('POST', '/dashboard/pages', ['recipient' => 'One more']);
        $this->assertSame(PagesController::MAX_DRAFTS, Page::query()->count());
    }

    public function testTheEditorShowsEveryPartOfThePage(): void
    {
        $page = $this->makePage($this->owner, ['recipient' => 'Jinelin']);
        $this->makePhoto($page, 'https://cdn.test/a.jpg');
        $html = (string) $this->get('/dashboard/pages/' . $page->slug)->getBody();

        foreach (['id="message"', 'id="photos"', 'id="song"', 'id="lock"', 'id="design"', 'id="share"', 'id="plan"', 'id="delete-dialog"'] as $section) {
            $this->assertStringContainsString($section, $html);
        }
        $this->assertStringContainsString('First paragraph.' . "\n\n" . 'Second paragraph.', $html, 'the letter is editable as paragraphs');
        $this->assertStringContainsString('https://cdn.test/a.jpg', $html);
        $this->assertStringContainsString('1 on this page', $html);
        $this->assertMatchesRegularExpression('#<b id="usage-used">1</b> of <span id="usage-total">6</span> used · <span id="usage-left">5 left</span>#', $html, 'the account\'s photos: used, of, left');
        $this->assertStringContainsString('Included: 6 with the free preview', $html);
        $this->assertStringContainsString('It comes with the Sweetheart and Forever plans', $html, 'subdomain upsell on a free page');
        $this->assertStringContainsString('/dashboard/pages/' . $page->slug . '/qr', $html);
    }

    public function testSavingTheLetterAndWhoItIsFor(): void
    {
        $page = $this->makePage($this->owner);
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/message', [
            'recipient' => 'Yam',
            'message' => "Hello you.\nStill the same paragraph.\n\nA second one.",
        ]);

        $page = $page->fresh();
        $this->assertSame('Yam', $page->recipient);
        $this->assertSame(['Hello you. Still the same paragraph.', 'A second one.'], $page->paragraphs());
    }

    public function testAnEmptyLetterIsRefusedAndNothingChanges(): void
    {
        $page = $this->makePage($this->owner);
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/message', ['recipient' => 'Yam', 'message' => "  \n "]);
        $this->assertSame(['First paragraph.', 'Second paragraph.'], $page->fresh()->paragraphs());
        $this->assertSame('Jinelin', $page->fresh()->recipient);
    }

    public function testSettingAndRemovingTheSong(): void
    {
        $page = $this->makePage($this->owner);
        $this->youtubeAnswers(new GuzzleResponse(200, [], (string) json_encode(['title' => 'Palagi', 'author_name' => 'TJ Monterde'])));

        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/song', ['url' => 'https://youtu.be/fu9yk7gCTbc']);
        $page = $page->fresh();
        $this->assertSame('fu9yk7gCTbc', $page->song_id);
        $this->assertSame('Palagi', $page->song_title);
        $this->assertSame('TJ Monterde', $page->song_artist);

        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/song', ['remove' => '1']);
        $this->assertNull($page->fresh()->song_id);
    }

    public function testOnlyYouTubeLinksBecomeTheSong(): void
    {
        $page = $this->makePage($this->owner);
        $this->youtubeAnswers(new GuzzleResponse(404));
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/song', ['url' => 'https://vimeo.com/123']);
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/song', ['url' => 'https://youtu.be/AAAAAAAAAAA']);
        $this->assertNull($page->fresh()->song_id);
    }

    public function testTurningOnTheLockScreenNeedsAnAnswerThatIsStoredHashed(): void
    {
        $page = $this->makePage($this->owner);
        $url = '/dashboard/pages/' . $page->slug . '/lock';

        $this->submit('PUT', $url, ['gate_enabled' => '1', 'gate_question' => 'What do I call you?', 'gate_eyebrow' => 'For you', 'gate_password' => '']);
        $this->assertFalse($page->fresh()->hasGate(), 'no answer, no lock');

        $this->submit('PUT', $url, ['gate_enabled' => '1', 'gate_question' => 'What do I call you?', 'gate_eyebrow' => 'For you', 'gate_password' => '  Yam  ']);
        $page = $page->fresh();
        $this->assertTrue($page->hasGate());
        $this->assertSame('What do I call you?', $page->gate_question);
        $this->assertSame('For you', $page->gate_eyebrow);
        $this->assertNotSame('yam', $page->gate_password);
        $this->assertTrue($this->app->make(Hasher::class)->check('yam', (string) $page->gate_password), 'stored normalised and hashed');

        // an empty answer keeps the current one
        $this->submit('PUT', $url, ['gate_enabled' => '1', 'gate_question' => 'Changed?', 'gate_eyebrow' => '', 'gate_password' => '']);
        $page = $page->fresh();
        $this->assertTrue($this->app->make(Hasher::class)->check('yam', (string) $page->gate_password));
        $this->assertSame('Changed?', $page->gate_question);
        $this->assertSame(Page::DEFAULT_EYEBROW, $page->gate_eyebrow);

        $this->submit('PUT', $url, ['gate_question' => 'Changed?']);
        $this->assertFalse($page->fresh()->hasGate(), 'unchecked turns it off');
    }

    public function testAnAnswerIsNeverKeptInTheSessionAfterAFailedSave(): void
    {
        $page = $this->makePage($this->owner);
        // an invalid question makes the save fail and the form input is flashed back for the next screen
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/lock', ['gate_enabled' => '1', 'gate_question' => '', 'gate_password' => 'our secret word']);

        $store = $this->app->make(\Naluz\Session\Store::class);
        $everything = (new \ReflectionProperty($store, 'data'))->getValue($store); // flashed values included
        $this->assertStringNotContainsString('our secret word', (string) json_encode($everything));

        $html = (string) $this->get('/dashboard/pages/' . $page->slug)->getBody();
        $this->assertStringNotContainsString('our secret word', $html);
        $old = (array) $store->get('old');
        $this->assertArrayHasKey('gate_question', $old, 'the rest of the input is kept');
        $this->assertArrayNotHasKey('gate_password', $old);
    }

    public function testChangingTheDesign(): void
    {
        $page = $this->makePage($this->owner);
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/design', ['layout' => 'polaroid', 'theme' => 'midnight']);
        $this->assertSame('polaroid', $page->fresh()->layout);
        $this->assertSame('midnight', $page->fresh()->theme);

        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/design', ['layout' => 'grid', 'theme' => 'neon']);
        $this->assertSame('polaroid', $page->fresh()->layout, 'unknown layouts are refused');
        $this->assertSame('midnight', $page->fresh()->theme);
    }

    public function testALivePageUsesTheTemplatesOfItsPlanAndPreviewsTheRest(): void
    {
        $page = $this->makeLivePage($this->owner, ['plan' => 'sweet', 'expires_at' => '2099-01-01 00:00:00', 'layout' => 'classic']);
        $url = '/dashboard/pages/' . $page->slug . '/design';

        $this->submit('PUT', $url, ['layout' => 'stars', 'theme' => 'midnight']);
        $this->assertSame('classic', $page->fresh()->layout, 'Sweet does not include the Starry sky');
        $html = (string) $this->get('/dashboard/pages/' . $page->slug)->getBody();
        $this->assertStringContainsString('The Starry sky template comes with Sweetheart and Forever. Upgrade the plan to use it on this page.', $html);
        $this->assertStringContainsString('data-locked="Starry sky comes with Sweetheart and Forever.', $html, 'the picker says so before saving');
        $this->assertStringNotContainsString('data-locked="Envelope', $html);
        $this->assertStringContainsString('<span class="plan-badge">Sweetheart and Forever</span>', $html);

        $this->submit('PUT', $url, ['layout' => 'envelope', 'theme' => 'midnight']);
        $this->assertSame('envelope', $page->fresh()->layout);
        $preview = (string) $this->get('/dashboard/pages/' . $page->slug . '/preview?layout=stars')->getBody();
        $this->assertStringContainsString('data-layout="stars"', $preview, 'the owner can still preview it');

        $draft = $this->makePage($this->owner);
        $this->submit('PUT', '/dashboard/pages/' . $draft->slug . '/design', ['layout' => 'stars', 'theme' => 'rose']);
        $this->assertSame('stars', $draft->fresh()->layout, 'a draft may try any template');
        $this->assertStringNotContainsString('data-locked=', (string) $this->get('/dashboard/pages/' . $draft->slug)->getBody());
    }

    public function testThePlanSectionSellsMonthlyYearlyAndOnce(): void
    {
        $page = $this->makePage($this->owner);
        $html = (string) $this->get('/dashboard/pages/' . $page->slug)->getBody();
        foreach (['₱200 a month', '₱2,400 a year', '₱350 a month', '₱4,200 a year', '₱4,999 once', 'Most popular'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('months free', $html, 'no yearly discount any more');
        $this->assertSame(5, substr_count($html, 'name="period" value="'), 'Sweet and Sweetheart monthly and yearly, Forever once');

        $page->forceFill(['plan' => 'sweetheart', 'expires_at' => '2099-01-01 00:00:00', 'published_at' => '2026-01-01 00:00:00'])->save();
        $html = (string) $this->get('/dashboard/pages/' . $page->slug)->getBody();
        $this->assertStringContainsString('Renew Sweetheart', $html);
        $this->assertStringContainsString('Upgrade to Forever', $html);
        $this->assertStringNotContainsString('₱200 a month', $html, 'no way down');
    }

    public function testThePlanThePageIsOnIsMarkedCurrent(): void
    {
        $page = $this->makePage($this->owner);
        $url = '/dashboard/pages/' . $page->slug;
        $this->assertStringNotContainsString('tag-current', (string) $this->get($url)->getBody(), 'a free draft is on no plan');

        $page->forceFill(['plan' => 'sweetheart', 'expires_at' => '2099-01-01 00:00:00', 'published_at' => '2026-01-01 00:00:00'])->save();
        $html = (string) $this->get($url)->getBody();
        $this->assertSame(1, substr_count($html, 'tag-current'));
        $this->assertMatchesRegularExpression('#tag-current">Current</span>.*?Renew Sweetheart#s', $html, 'the Current tag is on the plan it is on');
        $this->assertStringContainsString('Online until', $html);
        $this->assertStringContainsString('Upgrade to Forever', $html);
        $this->assertStringNotContainsString('<strong>Sweet</strong>', $html, 'a lower plan is not offered');

        $page->forceFill(['plan' => 'forever', 'expires_at' => null])->save();
        $html = (string) $this->get($url)->getBody();
        $this->assertSame(1, substr_count($html, 'tag-current'), 'Forever has nothing more to buy but is still shown as the current plan');
        $this->assertStringContainsString('Online forever', $html);
        $this->assertStringNotContainsString('name="plan" value="forever"', $html);

        $page->forceFill(['plan' => 'sweet', 'expires_at' => '2020-01-01 00:00:00', 'published_at' => '2019-01-01 00:00:00'])->save();
        $html = (string) $this->get($url)->getBody();
        $this->assertStringContainsString('tag-current">Expired</span>', $html);
        $this->assertStringContainsString('Renew Sweet', $html);
    }

    public function testEveryTemplateCanBeChosenWithEveryTheme(): void
    {
        $page = $this->makePage($this->owner);
        foreach (array_keys(Designs::LAYOUTS) as $layout) {
            foreach (array_keys(Designs::pickableThemes()) as $theme) {
                $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/design', ['layout' => $layout, 'theme' => $theme]);
                $fresh = $page->fresh();
                $this->assertSame([$layout, $theme], [$fresh->layout, $fresh->theme]);
            }
        }
        $html = (string) $this->get('/dashboard/pages/' . $page->slug)->getBody();
        foreach (Designs::LAYOUTS as $key => $template) {
            $this->assertStringContainsString('name="layout" value="' . $key . '"', $html, "{$key} is in the picker");
            $this->assertStringContainsString($template['play'], $html);
        }
        $this->assertStringContainsString('Template</legend>', $html);
        $this->assertStringNotContainsString('name="theme" value="halloween"', $html, 'the seasonal colours are not a choice');

        $before = $page->fresh()->theme;
        $this->submit('PUT', '/dashboard/pages/' . $page->slug . '/design', ['layout' => 'classic', 'theme' => 'halloween']);
        $this->assertSame($before, $page->fresh()->theme, 'a seasonal theme cannot be saved on its own');
    }

    public function testThePreviewShowsTheDraftWithAnyDesignBeforeItIsSaved(): void
    {
        $page = $this->makePage($this->owner, ['layout' => 'classic', 'theme' => 'rose']);
        $this->makePhoto($page, 'https://cdn.test/a.jpg');

        $response = $this->get('/dashboard/pages/' . $page->slug . '/preview?layout=cinematic&theme=blush');
        $html = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('data-layout="cinematic"', $html);
        $this->assertStringContainsString('data-theme="blush"', $html);
        $this->assertStringContainsString('data-mode="preview"', $html);
        $this->assertStringContainsString('https:\/\/cdn.test\/a.jpg', $html, 'the preview carries the content');
        $this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
        $this->assertSame('classic', $page->fresh()->layout, 'previewing saves nothing');

        $bad = (string) $this->get('/dashboard/pages/' . $page->slug . '/preview?layout=%3Cscript%3E')->getBody();
        $this->assertStringContainsString('data-layout="classic"', $bad);
    }

    public function testTheQrCodeIsAnSvgOfThePageLink(): void
    {
        $page = $this->makePage($this->owner, ['recipient' => 'Jinelin Basela']);
        $response = $this->get('/dashboard/pages/' . $page->slug . '/qr');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('image/svg+xml', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('<svg', (string) $response->getBody());

        $page->forceFill(['plan' => 'forever', 'subdomain' => 'jinelin'])->save();
        $this->assertSame(
            \App\Services\QrCode::svg('https://love.test/p/' . $page->slug),
            (string) $this->get('/dashboard/pages/' . $page->slug . '/qr')->getBody(),
            'the QR code opens the permanent link, not the subdomain that can be released',
        );

        $download = $this->get('/dashboard/pages/' . $page->slug . '/qr?download=1');
        $this->assertSame('attachment; filename="for-jinelin-qr.svg"', $download->getHeaderLine('Content-Disposition'));
    }

    public function testDeletingAPageRemovesItsPhotosButKeepsItsOrders(): void
    {
        $page = $this->makePage($this->owner);
        $this->makePhoto($page, 'https://cdn.test/a.jpg', 1);
        $this->makePhoto($page, 'https://cdn.test/b.jpg', 2);
        $order = new Order();
        $order->forceFill(['reference' => 'LM-AAAA-BBBB', 'user_id' => $this->owner->id, 'page_id' => $page->id, 'plan' => 'sweet', 'period' => 'month', 'amount' => 20000, 'currency' => 'PHP', 'status' => 'paid', 'provider' => 'manual'])->save();
        $other = $this->makePage($this->owner);
        $kept = $this->makePhoto($other, 'https://cdn.test/kept.jpg');

        $this->submit('DELETE', '/dashboard/pages/' . $page->slug, ['confirm' => 'nope']);
        $this->assertNotNull($page->fresh(), 'not without typing "delete"');

        $response = $this->submit('DELETE', '/dashboard/pages/' . $page->slug, ['confirm' => 'delete']);
        $this->assertSame('/dashboard', $response->getHeaderLine('Location'));
        $this->assertNull($page->fresh());
        $this->assertSame([$kept->id], array_map('intval', Photo::query()->pluck('id')->all()), 'only the other page\'s photo is left');
        $this->assertSame(['pages/' . $page->slug . '/a.jpg', 'pages/' . $page->slug . '/b.jpg'], $this->disk->deleted, 'its stored files are deleted');
        $this->assertSame([(string) $kept->path], array_keys($this->disk->files));
        $this->assertSame(0, $this->db()->table('storage_trash')->count(), 'nothing left to retry');
        $this->assertNull($order->fresh()->page_id, 'the order stays on record');
        $this->assertStringContainsString('with its 2 photos, which no longer count towards your account', (string) $this->get('/dashboard')->getBody());
        $this->assertSame(1, $this->app->make(\App\Services\PhotoAllowance::class)->of($this->owner->id)['used']);
    }

    public function testAnotherOwnersPageIsInvisible(): void
    {
        $theirs = $this->makePage($this->makeUser('other@example.com'), ['recipient' => 'Not yours']);
        $photo = $this->makePhoto($theirs, 'https://cdn.test/theirs.jpg');
        $base = '/dashboard/pages/' . $theirs->slug;

        foreach (['', '/preview', '/qr'] as $path) {
            $this->assertSame(404, $this->get($base . $path)->getStatusCode(), "GET {$base}{$path}");
        }
        $writes = [
            ['PUT', $base . '/message', ['recipient' => 'Hacked', 'message' => 'Hacked']],
            ['PUT', $base . '/song', ['remove' => '1']],
            ['PUT', $base . '/lock', ['gate_question' => 'x']],
            ['PUT', $base . '/design', ['layout' => 'polaroid', 'theme' => 'blush']],
            ['PUT', $base . '/subdomain', ['subdomain' => 'stolen']],
            ['DELETE', $base . '/subdomain', []],
            ['POST', $base . '/orders', ['plan' => 'sweet', 'period' => 'month']],
            ['DELETE', $base . '/photos/' . $photo->id, []],
            ['DELETE', $base, ['confirm' => 'delete']],
        ];
        foreach ($writes as [$method, $uri, $data]) {
            $this->assertSame(404, $this->submit($method, $uri, $data)->getStatusCode(), "{$method} {$uri}");
        }
        $this->assertSame(404, $this->ajax('POST', $base . '/photos/order', ['order' => [$photo->id]])->getStatusCode());

        $fresh = $theirs->fresh();
        $this->assertSame('Not yours', $fresh->recipient);
        $this->assertSame('classic', $fresh->layout);
        $this->assertSame(1, Photo::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertStringNotContainsString('Not yours', (string) $this->get('/dashboard')->getBody());
    }

    public function testWritesNeedTheCsrfToken(): void
    {
        $page = $this->makePage($this->owner);
        $response = $this->form('POST', '/dashboard/pages/' . $page->slug . '/message', ['_method' => 'PUT', 'recipient' => 'X', 'message' => 'Y']);
        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('Jinelin', $page->fresh()->recipient);
    }
}
