<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Screen;
use App\Services\AdminStats;
use App\Services\Plans;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** The administrator's read-only view of the product: how many people signed up, who subscribes, what was paid. */
final class AdminController
{
    public function index(AdminStats $stats, Plans $plans): ResponseInterface
    {
        return Screen::render('admin.index', [
            'stats' => $stats->overview(),
            'latestUsers' => $stats->latestUsers(),
            'recentOrders' => $stats->recentOrders(),
            'plans' => $plans->all(),
            'currency' => $plans->currency(),
        ]);
    }

    /** Where the visitors came from (UTM tags and referrers) and what they turned into. ?days=7|30|90 */
    public function traffic(ServerRequestInterface $request, AdminStats $stats, Plans $plans): ResponseInterface
    {
        $days = (int) ($request->getQueryParams()['days'] ?? 30);
        return Screen::render('admin.traffic', [
            'traffic' => $stats->traffic(in_array($days, [7, 30, 90], true) ? $days : 30),
            'currency' => $plans->currency(),
        ]);
    }

    public function users(ServerRequestInterface $request, AdminStats $stats): ResponseInterface
    {
        $query = $request->getQueryParams();
        $filters = [];
        foreach (['q', 'type', 'status', 'role', 'subscribers'] as $key) {
            $filters[$key] = is_string($query[$key] ?? null) ? mb_substr(trim($query[$key]), 0, 100) : '';
        }
        $page = max(1, (int) ($query['page'] ?? 1));
        $result = $stats->users($filters, $page);

        return Screen::render('admin.users', [
            'filters' => $filters,
            'paginator' => $result['users'],
            'info' => $result['info'],
            'perPage' => AdminStats::PER_PAGE,
            'me' => (int) app(\Naluz\Auth\Auth::class)->id(),
        ]);
    }

    public function payments(ServerRequestInterface $request, AdminStats $stats, Plans $plans): ResponseInterface
    {
        $query = $request->getQueryParams();
        $filters = [];
        foreach (['q', 'status', 'provider'] as $key) {
            $filters[$key] = is_string($query[$key] ?? null) ? mb_substr(trim($query[$key]), 0, 100) : '';
        }
        $result = $stats->payments($filters, max(1, (int) ($query['page'] ?? 1)));

        return Screen::render('admin.payments', [
            'filters' => $filters,
            'summary' => $stats->paymentSummary(),
            'paginator' => $result['orders'],
            'emails' => $result['emails'],
            'providers' => $result['providers'],
            'plans' => $plans->all(),
            'currency' => $plans->currency(),
            'gateway' => (string) config('saas.payments.driver', 'none'),
        ]);
    }
}
