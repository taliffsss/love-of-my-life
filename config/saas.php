<?php

declare(strict_types=1);

// The multi-page product: plans, the domain that dedicated subdomains live under, and how payments are taken.

$appHost = (string) (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost');

return [
    // Brand shown in the dashboard, the landing page and e-mails.
    'brand' => env('APP_NAME', 'Love of My Life'),

    // Main domain of the product. Sweetheart and Forever pages can get <name>.<domain>; point a wildcard DNS record (*.domain) and
    // a wildcard certificate at this app and new subdomains work the moment they are claimed (see DEPLOY.md).
    'domain' => strtolower(trim((string) (env('SAAS_DOMAIN') ?: $appHost), '. ')),

    // Never handed out as a page subdomain.
    'reserved_subdomains' => [
        'about', 'account', 'accounts', 'admin', 'api', 'app', 'assets', 'auth', 'billing', 'blog', 'cdn', 'checkout',
        'dashboard', 'demo', 'dev', 'docs', 'email', 'ftp', 'help', 'imap', 'legal', 'login', 'logout', 'mail', 'media',
        'mx', 'ns1', 'ns2', 'pay', 'payment', 'payments', 'pop', 'pricing', 'privacy', 'register', 'root', 'secure',
        'signup', 'smtp', 'staging', 'static', 'status', 'support', 'terms', 'test', 'webmail', 'www',
    ],

    // Google Analytics 4 measurement id. On in production by default; empty (GA_MEASUREMENT_ID=) turns it off, and development and
    // tests send nothing. Only the marketing site, sign-in and dashboard carry the tag: never a page someone made for their
    // loved one (those are not part of the product layout) and never the admin area (see App\Http\Screen).
    'analytics_id' => (string) env('GA_MEASUREMENT_ID', env('APP_ENV', 'production') === 'production' ? 'G-M62J7X95ED' : ''),

    'currency' => 'PHP',

    // Dates are stored in UTC; screens show them in this time zone (App\Support\Time).
    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Manila'),

    // none   = no online checkout yet: the dashboard explains how to pay and an admin activates the plan
    //          (php bin/grant-plan.php <page> <plan>)
    // fake   = a test checkout that marks the order paid (refused when APP_ENV=production)
    // paymongo / xendit = a hosted checkout; the provider's webhook marks the order paid (see App\Services\Payments)
    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'auto'), // auto (PayMongo once its key is set, else none) | none | fake | paymongo | xendit
        // PayMongo checkout sessions. The webhook (<APP_URL>/api/webhooks/paymongo) is checked with its signing secret.
        'paymongo' => [
            'secret_key' => (string) env('PAYMONGO_SECRET_KEY', ''),
            // PayMongo Subscriptions (cards and Maya charged again by PayMongo) must be enabled on the account by PayMongo support first
            'subscriptions' => filter_var(env('PAYMONGO_SUBSCRIPTIONS', false), FILTER_VALIDATE_BOOLEAN),
            // needed with the secret key to take GCash, PayMaya and cards on our own page (PayMongo makes the payment method and attaches it with the public key)
            'public_key' => (string) env('PAYMONGO_PUBLIC_KEY', ''),
            'webhook_secret' => (string) env('PAYMONGO_WEBHOOK_SECRET', ''),
            'methods' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYMONGO_METHODS', 'gcash,paymaya,card,grab_pay'))))),
        ],
        // Xendit invoices. The webhook (<APP_URL>/api/webhooks/xendit) is checked with the dashboard's verification token.
        'xendit' => [
            'secret_key' => (string) env('XENDIT_SECRET_KEY', ''),
            'callback_token' => (string) env('XENDIT_CALLBACK_TOKEN', ''),
        ],
        // shown to customers while payments are manual
        'manual_instructions' => env('PAYMENTS_MANUAL_INSTRUCTIONS', 'Send the amount by GCash or Maya and reply with your order reference; your page goes live as soon as we confirm it.'),
    ],

    // How long a payment keeps a page online, in months; null = for good.
    'periods' => ['month' => 1, 'year' => 12, 'once' => null],

    // The plans, cheapest first (an upgrade is a move to the right). Prices are in centavos, per period: `month` and
    // `year` add their months to the time left (a year costs twelve months: there is no yearly discount), `once` never
    // expires. `photos`: what the plan adds to the account's photo allowance while its page is live (App\Services\PhotoAllowance);
    // an account with no live page gets the free preview's. `templates`: the templates a live page may use, 'all' or a list
    // of App\Services\Designs keys; the free preview shows every template so people can try them before choosing a plan. A
    // free page is a private draft: only its owner can preview it. See marketing/PRICING.md for the reasoning behind the
    // numbers. `photos` is not a plan key: orders use it for photo packs.
    'plans' => [
        'free' => ['name' => 'Free preview', 'prices' => [], 'photos' => 6, 'publish' => false, 'subdomain' => false,
            'templates' => 'all', 'popular' => false,
            'blurb' => 'Build the whole page and preview it. Only you can see it.'],
        'sweet' => ['name' => 'Sweet', 'prices' => ['month' => 20000, 'year' => 240000], 'photos' => 12, 'publish' => true,
            'subdomain' => false, 'templates' => ['classic', 'cinematic', 'polaroid', 'envelope', 'timeline'], 'popular' => false,
            'blurb' => 'Your page online, with 12 photos and 5 templates.'],
        'sweetheart' => ['name' => 'Sweetheart', 'prices' => ['month' => 35000, 'year' => 420000], 'photos' => 40, 'publish' => true,
            'subdomain' => true, 'templates' => 'all', 'popular' => true,
            'blurb' => 'All 10 templates, 40 photos and an address of its own.'],
        'forever' => ['name' => 'Forever', 'prices' => ['once' => 499900], 'photos' => 60, 'publish' => true,
            'subdomain' => true, 'templates' => 'all', 'popular' => false,
            'blurb' => 'Pay once and it stays online for good: 60 photos, every template, its own address.'],
    ],

    // More photos for an account that has used its allowance: bought once, kept for good, shared by all its pages.
    // An order buys 1 to `max_packs` packs.
    'photo_pack' => ['photos' => 12, 'price' => 10000, 'max_packs' => 10],

    // The largest photo accepted, before and after the browser shrinks it (10 MB, as a phone or computer counts them).
    // PHP and nginx accept bodies up to 16 MB (docker/php/php.ini, docker/nginx/default.conf), so larger files reach
    // the app and get a clear answer instead of a bare error.
    'max_upload_bytes' => 10 * 1024 * 1024,
];
