<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Screen;
use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Services\PhotoAllowance;
use App\Services\Plans;
use App\Services\Subscriptions;
use Naluz\Auth\Auth;
use Psr\Http\Message\ResponseInterface;

/** The account's billing: what it is subscribed to, what it has paid, the details invoices carry, and its photo allowance. */
final class BillingController
{
    public function show(Auth $auth, Plans $plans, Subscriptions $subscriptions, PhotoAllowance $allowance): ResponseInterface
    {
        $user = $auth->user();
        assert($user instanceof User);
        $orders = Order::query()->withoutCache()->useWritePdo()->where('user_id', $user->id)->orderBy('id', 'desc')->limit(100)->get()->all();
        $pages = [];
        foreach (Page::query()->withoutCache()->useWritePdo()->where('user_id', $user->id)->get()->all() as $page) {
            $pages[$page->id] = $page;
        }
        $paid = array_filter($orders, static fn (Order $o): bool => $o->status === Order::PAID);

        return Screen::render('account.billing', [
            'section' => 'billing',
            'plans' => $plans->all(),
            'subscriptions' => $subscriptions->forUser($user->id),
            'orders' => $orders,
            'pages' => $pages,
            'due' => array_values(array_filter($orders, static fn (Order $o): bool => $o->status === Order::PENDING && $o->subscription_id !== null)),
            'totalPaid' => array_sum(array_map(static fn (Order $o): int => $o->amount, $paid)),
            'currency' => $orders !== [] ? $orders[0]->currency : (string) config('saas.currency', 'PHP'),
            'usage' => $allowance->of($user->id),
            'packs' => $allowance->packOptions(),
        ]);
    }
}
