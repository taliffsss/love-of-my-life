<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Photo;
use App\Models\User;
use Naluz\Database\Connection;
use Naluz\Security\Hasher;

/**
 * Turns the original single-page site (config/site.php, storage/app/{carousel,letter,song}.json and public/photos) into
 * a Forever page owned by an account, served on the original domain. Run once with bin/import-legacy.php.
 */
final class LegacyImporter
{
    public function __construct(
        private readonly CarouselStore $carousel,
        private readonly PhotoCatalog $catalog,
        private readonly LetterStore $letters,
        private readonly SongStore $songs,
        private readonly Hasher $hasher,
        private readonly Connection $db,
    ) {
    }

    /**
     * @param string $domain the host the site was served on, e.g. old-site.example.com
     * @param string $answer the answer of the old lock screen (it lived in app.js)
     * @throws \RuntimeException when that domain already belongs to a page
     */
    public function import(User $owner, string $domain, string $answer): Page
    {
        $domain = strtolower(trim($domain));
        if ($domain === '' || Page::query()->withoutCache()->useWritePdo()->where('custom_domain', $domain)->exists()) {
            throw new \RuntimeException("A page is already served on [{$domain}]; nothing was imported.");
        }
        $answer = \App\Http\Controllers\SiteController::normaliseAnswer($answer);
        if ($answer === '') {
            throw new \InvalidArgumentException('The lock screen answer is required.');
        }

        // keys of the photos stored on Spaces, so deleting one from the dashboard also removes the file
        $keys = array_column($this->carousel->all(), 'key', 'url');
        $song = $this->songs->get();

        return $this->db->transaction(function () use ($owner, $domain, $answer, $keys, $song): Page {
            $page = new Page();
            $page->forceFill([
                'user_id' => $owner->id,
                'slug' => Page::newSlug(),
                'custom_domain' => $domain,
                'recipient' => mb_substr($this->letters->name() ?: 'my love', 0, 60),
                'letter' => $this->letters->paragraphs(),
                'song_id' => is_string($song['id'] ?? null) && $song['id'] !== '' ? $song['id'] : null,
                'song_title' => (string) ($song['title'] ?? ''),
                'song_artist' => (string) ($song['artist'] ?? ''),
                'gate_enabled' => true,
                'gate_password' => $this->hasher->make($answer),
                'gate_eyebrow' => Page::DEFAULT_EYEBROW,
                'gate_question' => Page::DEFAULT_QUESTION,
                'layout' => Designs::DEFAULT_LAYOUT,
                'theme' => Designs::DEFAULT_THEME,
                'plan' => 'forever',
                'expires_at' => null,
                'published_at' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                // the original site was meant to be found
                'searchable' => true,
            ])->save();

            foreach ($this->catalog->all() as $i => $url) {
                $photo = new Photo();
                $photo->forceFill([
                    'page_id' => $page->id,
                    'disk' => isset($keys[$url]) ? 'spaces' : null,
                    'path' => $keys[$url] ?? null,
                    'url' => $url,
                    'position' => $i + 1,
                ])->save();
            }
            return $page;
        });
    }
}
