<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Naluz\Database\Connection;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Where visitors come from, for the marketing links (UTM tags: marketing/UTM.md).
 *
 * - `record()` stores one row per visit of a public page: the tags of the link (`?utm_source=facebook&utm_medium=paid_social&utm_campaign=halloween-2026`)
 *   or, without tags, the site that sent the visitor ("referral") or "direct". A repeat of the same campaign within 30 minutes by the same visitor is not counted again.
 * - The first tagged (or referred) visit sets the `lom_a` cookie for 30 days: first touch. It is saved on the account that signs up, so the dashboard can
 *   say which campaign brought which sign-ups and which customers.
 * - What is stored: a random visitor id (`lom_v`, first-party), the tags, the referring host and the path. No IP address, no user agent, no personal data.
 */
final class Traffic
{
    public const VISITOR = 'lom_v';
    public const ATTRIBUTION = 'lom_a';
    public const ATTRIBUTION_DAYS = 30;
    public const VISITOR_DAYS = 395;
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|curl|wget|python|monitor|uptime|lighthouse/i';
    private const SOCIAL = [
        'facebook.com' => 'facebook', 'm.facebook.com' => 'facebook', 'l.facebook.com' => 'facebook', 'lm.facebook.com' => 'facebook',
        'instagram.com' => 'instagram', 'l.instagram.com' => 'instagram', 'tiktok.com' => 'tiktok', 'vm.tiktok.com' => 'tiktok',
        'google.com' => 'google', 'bing.com' => 'bing', 't.co' => 'twitter', 'x.com' => 'twitter', 'youtube.com' => 'youtube', 'm.youtube.com' => 'youtube',
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    /** A tag as it is stored: lower case, [a-z0-9._~-], at most 60 characters; null when nothing is left. */
    public static function clean(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = preg_replace('/[^a-z0-9._~-]+/', '-', mb_strtolower(trim($value))) ?? '';
        $value = mb_substr(trim($value, '-'), 0, 60);
        return $value === '' ? null : $value;
    }

    public function isBot(ServerRequestInterface $request): bool
    {
        $agent = $request->getHeaderLine('User-Agent');
        return $agent === '' || preg_match(self::BOTS, $agent) === 1;
    }

    /**
     * The tags of this request: from the link when it has any, else from the referring site, else direct.
     *
     * @return array{source:string,medium:string,campaign:?string,content:?string,term:?string,referrer:?string,tagged:bool}
     */
    public function tags(ServerRequestInterface $request): array
    {
        $q = $request->getQueryParams();
        $host = $this->referrerHost($request);
        $source = self::clean($q['utm_source'] ?? null);
        $medium = self::clean($q['utm_medium'] ?? null);
        $campaign = self::clean($q['utm_campaign'] ?? null);
        if ($source !== null || $medium !== null || $campaign !== null) {
            return [
                'source' => $source ?? ($host !== null ? (self::SOCIAL[$host] ?? $host) : 'unknown'), 'medium' => $medium ?? 'unknown', 'campaign' => $campaign,
                'content' => self::clean($q['utm_content'] ?? null), 'term' => self::clean($q['utm_term'] ?? null), 'referrer' => $host, 'tagged' => true,
            ];
        }
        if ($host !== null) {
            return ['source' => self::SOCIAL[$host] ?? $host, 'medium' => 'referral', 'campaign' => null, 'content' => null, 'term' => null, 'referrer' => $host, 'tagged' => false];
        }
        return ['source' => 'direct', 'medium' => 'none', 'campaign' => null, 'content' => null, 'term' => null, 'referrer' => null, 'tagged' => false];
    }

    /** The host that sent the visitor, unless it is this site. */
    private function referrerHost(ServerRequestInterface $request): ?string
    {
        $host = parse_url($request->getHeaderLine('Referer'), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = preg_replace('/^www\./', '', mb_strtolower($host)) ?? '';
        $own = preg_replace('/^www\./', '', mb_strtolower($request->getUri()->getHost())) ?? '';
        return $host === $own || str_ends_with($host, '.' . $own) ? null : mb_substr($host, 0, 100);
    }

    /** Stores the visit (once per visitor and campaign per 30 minutes). @param array<string,mixed> $tags */
    public function record(string $visitor, array $tags, string $path, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $recent = $this->db->table('visits')->where('visitor', '=', $visitor)->where('source', '=', $tags['source'])->where('medium', '=', $tags['medium'])
            ->where('created_at', '>=', $now->modify('-30 minutes')->format('Y-m-d H:i:s'));
        $tags['campaign'] === null ? $recent->whereNull('campaign') : $recent->where('campaign', '=', $tags['campaign']);
        if ($recent->count() > 0) {
            return false;
        }
        $this->db->table('visits')->insert([
            'visitor' => $visitor, 'source' => $tags['source'], 'medium' => $tags['medium'], 'campaign' => $tags['campaign'], 'content' => $tags['content'],
            'term' => $tags['term'], 'referrer' => $tags['referrer'], 'path' => mb_substr($path, 0, 100), 'created_at' => $now->format('Y-m-d H:i:s'),
        ]);
        return true;
    }

    /** @param array<string,mixed> $tags */
    public function attributionCookieValue(array $tags): string
    {
        return rawurlencode((string) json_encode([$tags['source'], $tags['medium'], $tags['campaign'], $tags['content']], JSON_UNESCAPED_SLASHES));
    }

    /** The first-touch tags kept in the cookie, or null. @return array{source:string,medium:string,campaign:?string,content:?string}|null */
    public function attribution(ServerRequestInterface $request): ?array
    {
        $raw = $request->getCookieParams()[self::ATTRIBUTION] ?? null;
        $list = is_string($raw) ? json_decode(rawurldecode($raw), true) : null;
        if (!is_array($list) || count($list) !== 4 || self::clean($list[0] ?? null) === null) {
            return null;
        }
        return ['source' => (string) self::clean($list[0]), 'medium' => self::clean($list[1]) ?? 'unknown', 'campaign' => self::clean($list[2]), 'content' => self::clean($list[3])];
    }

    /** Saves the first-touch tags on the account that has just signed up. */
    public function claim(ServerRequestInterface $request, User $user): void
    {
        $tags = $this->attribution($request);
        if ($tags === null) {
            return;
        }
        $this->db->table('users')->where('id', '=', $user->id)->update([
            'utm_source' => $tags['source'], 'utm_medium' => $tags['medium'], 'utm_campaign' => $tags['campaign'], 'utm_content' => $tags['content'],
        ]);
    }
}
