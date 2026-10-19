#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Confirms a payment received outside the site (GCash, Maya, bank transfer) and puts the plan on the page, or the
 * photos of a photo pack order on the account.
 *
 *   php bin/grant-plan.php LM-7KQ2-9XDA [--ref="GCash 1234567890"]                  the order a customer created in the dashboard
 *   php bin/grant-plan.php --page=<slug> --plan=sweetheart --period=month [--ref=…]  no order yet: creates one, marks it paid
 *   php bin/grant-plan.php --email=<account e-mail> --packs=2 [--ref=…]             photo packs, no order yet: same
 *
 * Periods: month, year (Sweet, Sweetheart) or once (Forever); see config/saas.php.
 *
 * Safe to run twice: an order that is already paid is left alone.
 */

use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Services\Billing;

if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}

$app = require __DIR__ . '/../bootstrap/app.php';
$app->registerErrorHandler();
$app->boot();

// --name=value options and one positional order reference, in any order
$options = [];
$reference = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(page|plan|period|ref|email|packs)=(.*)$/s', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    } elseif (!str_starts_with($arg, '--')) {
        $reference = $arg;
    }
}
$billing = $app->make(Billing::class);
$providerRef = isset($options['ref']) ? mb_substr((string) $options['ref'], 0, 100) : null;

if ($providerRef !== null && Order::withProviderRef($providerRef) !== null) {
    fwrite(STDERR, "The payment reference {$providerRef} is already recorded on another order.\n");
    exit(1);
}

if (is_string($reference) && $reference !== '') {
    $order = Order::query()->where('reference', strtoupper($reference))->first();
    if (!$order instanceof Order) {
        fwrite(STDERR, "No order {$reference}.\n");
        exit(1);
    }
} elseif (isset($options['page'], $options['plan'], $options['period'])) {
    $page = Page::query()->where('slug', (string) $options['page'])->first();
    if (!$page instanceof Page) {
        fwrite(STDERR, "No page {$options['page']}.\n");
        exit(1);
    }
    try {
        $order = $billing->createOrder($page->user_id, $page, (string) $options['plan'], (string) $options['period'], 'manual');
    } catch (DomainException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
} elseif (isset($options['email'], $options['packs'])) {
    $user = User::query()->where('email', User::normaliseEmail((string) $options['email']))->first();
    if (!$user instanceof User) {
        fwrite(STDERR, "No account {$options['email']}.\n");
        exit(1);
    }
    try {
        $order = $billing->createPhotoOrder((int) $user->id, null, (int) $options['packs'], 'manual');
    } catch (DomainException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
} else {
    fwrite(STDERR, "Usage: php bin/grant-plan.php <order reference> [--ref=…]  |  php bin/grant-plan.php --page=<slug> --plan=<plan> --period=month|year|once [--ref=…]"
        . "  |  php bin/grant-plan.php --email=<e-mail> --packs=<n> [--ref=…]\n");
    exit(2);
}

if ($billing->markPaid($order, 'manual', $providerRef)) {
    if ($order->isPhotoPack()) {
        fwrite(STDOUT, "Order {$order->reference} is paid: {$order->photos} photos were added to the account.\n");
        exit(0);
    }
    $page = $order->page_id !== null ? Page::query()->where('id', $order->page_id)->first() : null;
    $until = $page instanceof Page && $page->expires_at !== null ? $page->expires_at->format('Y-m-d') : 'forever';
    fwrite(STDOUT, "Order {$order->reference} is paid: {$order->plan} ({$order->period}) is active until {$until}.\n");
    exit(0);
}
$order = $order->fresh() ?? $order;
fwrite(STDOUT, "Order {$order->reference} was already {$order->status}; nothing changed.\n");
if ($order->status === Order::CANCELLED) {
    fwrite(STDOUT, "It was cancelled because the page got a higher plan since. If money arrived for it, refund it.\n");
}
