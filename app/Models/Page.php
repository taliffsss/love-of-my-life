<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Plans;
use Naluz\Database\Orm\Model;
use Naluz\Database\Orm\Relations\BelongsTo;
use Naluz\Database\Orm\Relations\HasMany;

/**
 * One love page: who it is for, the letter, the song, the photos, the question asked before it opens, how it looks and
 * which plan keeps it online.
 *
 * Status: `draft` (free plan: only the owner can preview it), `active` (paid and not expired) or `expired`.
 *
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property ?string $subdomain
 * @property ?string $custom_domain
 * @property string $recipient
 * @property list<string> $letter
 * @property ?string $song_id
 * @property ?string $song_title
 * @property ?string $song_artist
 * @property bool $gate_enabled
 * @property ?string $gate_password
 * @property string $gate_eyebrow
 * @property string $gate_question
 * @property string $layout
 * @property string $theme
 * @property ?string $occasion
 * @property string $plan
 * @property ?\DateTimeImmutable $expires_at
 * @property ?\DateTimeImmutable $published_at
 * @property bool $searchable
 */
final class Page extends Model
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const EXPIRED = 'expired';

    public const DEFAULT_EYEBROW = 'For my love';
    public const DEFAULT_QUESTION = 'What is your endearment?';

    protected array $hidden = ['gate_password'];
    protected array $casts = [
        'user_id' => 'integer',
        'letter' => 'array',
        'gate_enabled' => 'boolean',
        'searchable' => 'boolean',
        'expires_at' => 'datetime',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    /** @return array{name:string,price:int,days:?int,photos:int,publish:bool,subdomain:bool,blurb:string} */
    public function planDetails(): array
    {
        $plans = (array) config('saas.plans');
        return app(Plans::class)->flagged($plans[$this->plan] ?? $plans['free']);
    }

    public function status(?\DateTimeImmutable $now = null): string
    {
        if (!$this->planDetails()['publish']) {
            return self::DRAFT;
        }
        $expires = $this->expires_at;
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $expires !== null && $expires <= $now ? self::EXPIRED : self::ACTIVE;
    }

    public function isLive(?\DateTimeImmutable $now = null): bool
    {
        return $this->status($now) === self::ACTIVE;
    }

    /** Only plans that include it (Sweetheart and Forever) may have a dedicated subdomain, and only while the `subdomains` feature is on. */
    public function allowsSubdomain(): bool
    {
        return (bool) $this->planDetails()['subdomain'];
    }

    public function hasGate(): bool
    {
        return $this->gate_enabled && (string) $this->gate_password !== '';
    }

    /** @return list<string> */
    public function paragraphs(): array
    {
        $letter = $this->letter;
        return is_array($letter) ? array_values(array_map('strval', $letter)) : [];
    }

    /** First name or the whole recipient name, for short labels. */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->recipient)) ?: [];
        return (string) ($parts[0] ?? $this->recipient);
    }

    /** A random, unguessable, URL-safe id: 10 characters from 31 letters and digits (~49 bits). */
    public static function newSlug(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        do {
            $slug = '';
            for ($i = 0; $i < 10; $i++) {
                $slug .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::query()->where('slug', $slug)->exists());
        return $slug;
    }
}
