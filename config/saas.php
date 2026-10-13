<?php

declare(strict_types=1);

// The multi-page product: plans, the domain that dedicated subdomains live under, and how payments are taken.

$appHost = (string) (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost');

return [
    // Brand shown in the dashboard, the landing page and e-mails.
    'brand' => env('APP_NAME', 'Love of My Life'),

    // Main domain of the product. Every Forever page can get <name>.<domain>; point a wildcard DNS record (*.domain) and
    // a wildcard certificate at this app and new subdomains work the moment they are claimed (see DEPLOY.md).
    'domain' => strtolower(trim((string) (env('SAAS_DOMAIN') ?: $appHost), '. ')),

    // Never handed out as a page subdomain.
    'reserved_subdomains' => [
        'about', 'account', 'accounts', 'admin', 'api', 'app', 'assets', 'auth', 'billing', 'blog', 'cdn', 'checkout',
        'dashboard', 'demo', 'dev', 'docs', 'email', 'ftp', 'help', 'imap', 'legal', 'login', 'logout', 'mail', 'media',
        'mx', 'ns1', 'ns2', 'pay', 'payment', 'payments', 'pop', 'pricing', 'privacy', 'register', 'root', 'secure',
        'signup', 'smtp', 'staging', 'static', 'status', 'support', 'terms', 'test', 'webmail', 'www',
    ],

    'currency' => 'PHP',

    // none   = no online checkout yet: the dashboard explains how to pay and an admin activates the plan
    //          (php bin/grant-plan.php <page> <plan>)
    // fake   = a test checkout that marks the order paid (refused when APP_ENV=production)
    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'none'),
        // shown to customers while payments are manual
        'manual_instructions' => env('PAYMENTS_MANUAL_INSTRUCTIONS', 'Send the amount by GCash or Maya and reply with your order reference; your page goes live as soon as we confirm it.'),
    ],

    // Prices are in centavos. `days` null = never expires. `photos` = how many photos the page may hold.
    // A free page is a private draft: only its owner can preview it.
    'plans' => [
        'free' => ['name' => 'Free preview', 'price' => 0, 'days' => null, 'photos' => 6, 'publish' => false, 'subdomain' => false,
            'blurb' => 'Build the whole page and preview it. Only you can see it.'],
        'basic' => ['name' => 'Basic', 'price' => 9900, 'days' => 365, 'photos' => 6, 'publish' => true, 'subdomain' => false,
            'blurb' => 'One year online, up to 6 photos, a song and your letter.'],
        'plus' => ['name' => 'Plus', 'price' => 19900, 'days' => 365, 'photos' => 20, 'publish' => true, 'subdomain' => false,
            'blurb' => 'One year online and up to 20 photos.'],
        'forever' => ['name' => 'Forever', 'price' => 39900, 'days' => null, 'photos' => 50, 'publish' => true, 'subdomain' => true,
            'blurb' => 'Never expires, up to 50 photos and an address of its own.'],
    ],

    'max_upload_bytes' => 12_000_000,
];
