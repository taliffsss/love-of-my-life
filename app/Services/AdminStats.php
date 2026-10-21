<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Support\Time;
use Naluz\Database\Connection;
use Naluz\Database\Paginator;

/**
 * The numbers on the admin dashboard and the list of users. Read-only. A *subscriber* is an account with at least one live
 * page on a paid plan (Page::status() = active); free drafts and expired pages do not count.
 */
final class AdminStats
{
    public const PER_PAGE = 20;
    public const DAYS = 30;

    public function __construct(private readonly Connection $db, private readonly Plans $plans)
    {
    }

    /**
     * @return array{
     *   users:int, admins:int, new7:int, new30:int, byType:array<string,int>, signups:array<string,int>,
     *   pages:int, pageStatus:array<string,int>, activeByPlan:array<string,int>, subscribers:int,
     *   orders:array{paid:int, pending:int, revenue:int, revenue30:int}
     * }
     */
    public function overview(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $since = fn (int $days): string => $now->modify("-{$days} days")->format('Y-m-d H:i:s');

        $byType = ['manual' => 0, 'google' => 0, 'facebook' => 0];
        foreach (array_keys($byType) as $type) {
            $byType[$type] = $this->db->table('users')->where('login_type', '=', $type)->count();
        }

        // signups per day for the last 30 days in the display time zone, oldest first (a day with none shows 0)
        $zone = Time::zone();
        $signups = [];
        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $signups[$now->setTimezone($zone)->modify("-{$i} days")->format('Y-m-d')] = 0;
        }
        foreach ($this->db->table('users')->select('created_at')->where('created_at', '>=', Time::dayStartUtc(self::DAYS - 1, $now))->get() as $row) {
            $day = (new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')))->setTimezone($zone)->format('Y-m-d');
            if (isset($signups[$day])) {
                $signups[$day]++;
            }
        }

        [$pageStatus, $activeByPlan, $subscriberIds, $pageTotal] = $this->pageFigures($now);

        $paid = $this->db->table('orders')->where('status', '=', Order::PAID);
        return [
            'users' => $this->db->table('users')->count(),
            'admins' => $this->db->table('users')->where('is_admin', '=', true)->count(),
            'new7' => $this->db->table('users')->where('created_at', '>=', $since(7))->count(),
            'new30' => $this->db->table('users')->where('created_at', '>=', $since(30))->count(),
            'byType' => $byType,
            'signups' => $signups,
            'pages' => $pageTotal,
            'pageStatus' => $pageStatus,
            'activeByPlan' => $activeByPlan,
            'subscribers' => count($subscriberIds),
            'orders' => [
                'paid' => (clone $paid)->count(),
                'pending' => $this->db->table('orders')->where('status', '=', 'pending')->count(),
                'revenue' => (int) (clone $paid)->sum('amount'),
                'revenue30' => (int) (clone $paid)->where('paid_at', '>=', $since(30))->sum('amount'),
            ],
        ];
    }

    /**
     * Where the traffic came from over the last `$days` days (visits recorded by App\Services\Traffic), and what it turned into. Visits and visitors
     * are counted in the period; sign-ups are the accounts created in it, with the campaign saved on them; customers are those accounts that have paid.
     *
     * @return array{days:int,total:array<string,int>,bySource:list<array<string,mixed>>,byCampaign:list<array<string,mixed>>,byContent:list<array<string,mixed>>}
     */
    public function traffic(int $days = self::DAYS, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $since = $now->modify("-{$days} days")->format('Y-m-d H:i:s');
        $none = '(none)';

        /** @var array<string,array<string,mixed>> $bySource */
        $bySource = $byCampaign = $byContent = [];
        $row = static fn (array $label): array => $label + ['visits' => 0, 'visitorIds' => [], 'signups' => 0, 'customers' => 0, 'revenue' => 0];
        $touch = static function (array &$table, string $key, array $label, callable $apply) use ($row): void {
            $table[$key] ??= $row($label);
            $apply($table[$key]);
        };

        $visitors = [];
        foreach ($this->db->table('visits')->select('visitor', 'source', 'medium', 'campaign', 'content')->where('created_at', '>=', $since)->get() as $v) {
            $visitors[(string) $v['visitor']] = true;
            $apply = static function (array &$r) use ($v): void {
                $r['visits']++;
                $r['visitorIds'][(string) $v['visitor']] = true;
            };
            $touch($bySource, $v['source'] . '|' . $v['medium'], ['source' => (string) $v['source'], 'medium' => (string) $v['medium']], $apply);
            $touch($byCampaign, ($v['campaign'] ?? $none) . '|' . $v['source'] . '|' . $v['medium'], ['campaign' => $v['campaign'] ?? $none, 'source' => (string) $v['source'], 'medium' => (string) $v['medium']], $apply);
            if ($v['content'] !== null) {
                $touch($byContent, ($v['campaign'] ?? $none) . '|' . $v['content'], ['campaign' => $v['campaign'] ?? $none, 'content' => (string) $v['content']], $apply);
            }
        }

        $users = $this->db->table('users')->select('id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content')->where('created_at', '>=', $since)->get()->all();
        $revenue = [];
        $ids = array_map(static fn (array $u): int => (int) $u['id'], $users);
        if ($ids !== []) {
            foreach ($this->db->table('orders')->select('user_id', 'amount')->where('status', '=', Order::PAID)->whereIn('user_id', $ids)->get() as $o) {
                $revenue[(int) $o['user_id']] = ($revenue[(int) $o['user_id']] ?? 0) + (int) $o['amount'];
            }
        }
        foreach ($users as $u) {
            $source = $u['utm_source'] ?? 'direct';
            $medium = $u['utm_medium'] ?? 'none';
            $paid = $revenue[(int) $u['id']] ?? null;
            $apply = static function (array &$r) use ($paid): void {
                $r['signups']++;
                if ($paid !== null) {
                    $r['customers']++;
                    $r['revenue'] += $paid;
                }
            };
            $touch($bySource, $source . '|' . $medium, ['source' => $source, 'medium' => $medium], $apply);
            $touch($byCampaign, ($u['utm_campaign'] ?? $none) . '|' . $source . '|' . $medium, ['campaign' => $u['utm_campaign'] ?? $none, 'source' => $source, 'medium' => $medium], $apply);
            if ($u['utm_content'] !== null) {
                $touch($byContent, ($u['utm_campaign'] ?? $none) . '|' . $u['utm_content'], ['campaign' => $u['utm_campaign'] ?? $none, 'content' => (string) $u['utm_content']], $apply);
            }
        }

        $finish = static function (array $table): array {
            $list = array_values(array_map(static function (array $r): array {
                $r['visitors'] = count($r['visitorIds']);
                unset($r['visitorIds']);
                $r['rate'] = $r['visitors'] > 0 ? round($r['signups'] / $r['visitors'] * 100, 1) : null;
                return $r;
            }, $table));
            usort($list, static fn (array $a, array $b): int => [$b['signups'], $b['visitors']] <=> [$a['signups'], $a['visitors']]);
            return $list;
        };

        return [
            'days' => $days,
            'total' => [
                'visits' => (int) $this->db->table('visits')->where('created_at', '>=', $since)->count(),
                'visitors' => count($visitors),
                'signups' => count($users),
                'customers' => count($revenue),
                'revenue' => array_sum($revenue),
            ],
            'bySource' => $finish($bySource),
            'byCampaign' => $finish($byCampaign),
            'byContent' => $finish($byContent),
        ];
    }

    /** @return list<Order> the latest paid orders */
    public function recentOrders(int $limit = 8): array
    {
        return Order::query()->where('status', Order::PAID)->orderBy('id', 'desc')->limit($limit)->get()->all();
    }

    /** @return list<User> */
    public function latestUsers(int $limit = 6): array
    {
        return User::query()->orderBy('id', 'desc')->limit($limit)->get()->all();
    }

    /**
     * One page of users, newest first. Filters: q (name or e-mail contains), type (manual, google, facebook), status (active, deactivated, suspended),
     * role (admin), subscribers (1).
     *
     * @param array{q?:string,type?:string,status?:string,role?:string,subscribers?:string} $filters
     * @return array{users:Paginator, info:array<int,array{pages:int,plans:list<string>}>}
     */
    public function users(array $filters, int $page): array
    {
        $query = User::query();
        $typed = trim((string) ($filters['q'] ?? ''));
        $q = mb_strtolower(trim(str_replace(['\\', '%', '_'], '', $typed)));
        if ($typed !== '' && $q === '') {
            $query->whereRaw('1 = 0'); // only wildcard characters: they are not wildcards here, and match nothing
        } elseif ($q !== '') {
            $like = '%' . $q . '%';
            $query->whereRaw('(lower(name) like ? or lower(email) like ?)', [$like, $like]);
        }
        if (in_array($filters['type'] ?? '', ['manual', 'google', 'facebook'], true)) {
            $query->where('login_type', $filters['type']);
        }
        if (in_array($filters['status'] ?? '', User::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if (($filters['role'] ?? '') === 'admin') {
            $query->where('is_admin', true);
        }
        if (($filters['subscribers'] ?? '') === '1') {
            $ids = $this->pageFigures(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))[2];
            $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn('id', $ids);
        }

        $users = $query->orderBy('id', 'desc')->paginate(self::PER_PAGE, max(1, $page));

        $info = [];
        $ids = array_map(static fn (User $u): int => $u->id, $users->items->all());
        if ($ids !== []) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            foreach ($this->pagesOf($this->db->table('pages')->select('id', 'user_id', 'plan', 'expires_at', 'published_at')->whereIn('user_id', $ids)->get()->all()) as $p) {
                $info[$p->user_id] ??= ['pages' => 0, 'plans' => []];
                $info[$p->user_id]['pages']++;
                if ($p->status($now) === Page::ACTIVE) {
                    $name = $p->planDetails()['name'];
                    if (!in_array($name, $info[$p->user_id]['plans'], true)) {
                        $info[$p->user_id]['plans'][] = $name;
                    }
                }
            }
        }

        return ['users' => $users, 'info' => $info];
    }

    /**
     * Money in and waiting, from the orders table (amounts in centavos).
     *
     * @return array{paid:int, paidAmount:int, pending:int, pendingAmount:int, cancelled:int, byProvider:array<string,int>}
     */
    public function paymentSummary(): array
    {
        $count = fn (string $status): int => $this->db->table('orders')->where('status', '=', $status)->count();
        $sum = fn (string $status): int => (int) $this->db->table('orders')->where('status', '=', $status)->sum('amount');
        $byProvider = [];
        foreach ($this->db->table('orders')->select('provider')->where('status', '=', Order::PAID)->groupBy('provider')->get() as $row) {
            $provider = (string) $row['provider'];
            $byProvider[$provider] = (int) $this->db->table('orders')->where('status', '=', Order::PAID)->where('provider', '=', $provider)->sum('amount');
        }
        ksort($byProvider);

        return [
            'paid' => $count(Order::PAID), 'paidAmount' => $sum(Order::PAID),
            'pending' => $count(Order::PENDING), 'pendingAmount' => $sum(Order::PENDING),
            'cancelled' => $count(Order::CANCELLED),
            'byProvider' => $byProvider,
        ];
    }

    /**
     * One page of orders (every payment), newest first. Filters: q (reference, provider reference or the buyer's e-mail
     * contains), status, provider.
     *
     * @param array{q?:string,status?:string,provider?:string} $filters
     * @return array{orders:Paginator, emails:array<int,string>, providers:list<string>}
     */
    public function payments(array $filters, int $page): array
    {
        $query = Order::query();
        $typed = trim((string) ($filters['q'] ?? ''));
        $q = mb_strtolower(trim(str_replace(['\\', '%', '_'], '', $typed)));
        if ($typed !== '' && $q === '') {
            $query->whereRaw('1 = 0');
        } elseif ($q !== '') {
            $like = '%' . $q . '%';
            // the provider's ids are encrypted: they are found by the whole id (its blind index), not by a part of it
            $query->whereRaw('(lower(reference) like ? or provider_ref_idx = ? or user_id in (select id from users where lower(email) like ?))', [$like, PaymentVault::index($typed), $like]);
        }
        if (in_array($filters['status'] ?? '', Order::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if (($filters['provider'] ?? '') !== '') {
            $query->where('provider', $filters['provider']);
        }

        $orders = $query->orderBy('id', 'desc')->paginate(self::PER_PAGE, max(1, $page));
        $emails = [];
        $ids = array_values(array_unique(array_map(static fn (Order $o): int => $o->user_id, $orders->items->all())));
        if ($ids !== []) {
            foreach (User::query()->whereIn('id', $ids)->get() as $user) {
                $emails[$user->id] = $user->email;
            }
        }
        $providers = array_map('strval', $this->db->table('orders')->select('provider')->groupBy('provider')->orderBy('provider')->pluck('provider')->all());

        return ['orders' => $orders, 'emails' => $emails, 'providers' => $providers];
    }

    /**
     * Counted over every page (only the columns that decide the status are read).
     *
     * @return array{0:array<string,int>,1:array<string,int>,2:list<int>,3:int} status counts, live pages per plan, subscriber user ids, total
     */
    private function pageFigures(\DateTimeImmutable $now): array
    {
        $status = [Page::DRAFT => 0, Page::ACTIVE => 0, Page::EXPIRED => 0];
        $byPlan = [];
        $subscribers = [];
        $total = 0;
        foreach ($this->pagesOf($this->db->table('pages')->select('id', 'user_id', 'plan', 'expires_at', 'published_at')->get()->all()) as $page) {
            $total++;
            $s = $page->status($now);
            $status[$s]++;
            if ($s === Page::ACTIVE) {
                $byPlan[$page->plan] = ($byPlan[$page->plan] ?? 0) + 1;
                $subscribers[$page->user_id] = true;
            }
        }

        return [$status, $byPlan, array_map('intval', array_keys($subscribers)), $total];
    }

    /** @param list<array<string,mixed>> $rows @return list<Page> */
    private function pagesOf(array $rows): array
    {
        /** @var list<Page> $pages */
        $pages = Page::query()->hydrate($rows);
        return $pages;
    }
}
