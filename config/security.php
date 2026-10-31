<?php

declare(strict_types=1);

// The "buy" form posts to us and is answered with a redirect to the provider's hosted checkout; a browser applies form-action
// to that redirect too, so the active provider's checkout host has to be allowed (and only that one).
$driver = (string) env('PAYMENTS_DRIVER', 'auto');
if ($driver === 'auto') {
    $driver = (string) env('PAYMONGO_SECRET_KEY', '') !== '' ? 'paymongo' : 'none';
}
// a card is sent from the browser straight to PayMongo (its number never reaches this server), which needs connect-src
$analytics = (string) env('GA_MEASUREMENT_ID', env('APP_ENV', 'production') === 'production' ? 'G-M62J7X95ED' : '') !== '';  // see config/saas.php
$connectSrc = "'self'" . ($driver === 'paymongo' ? ' https://api.paymongo.com' : '')
    . ($analytics ? ' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com' : '');
$formAction = "'self'" . match ($driver) {
    'paymongo' => ' https://checkout.paymongo.com',
    'xendit' => ' https://checkout.xendit.co https://checkout-staging.xendit.co',
    default => '',
};

return [
    // Override or disable (null) any default security header.
    'headers' => [
        // The product pages' fonts are self-hosted; the visitor pages (a love page, the expired notice) still use Google Fonts, the song from YouTube's embed, and uploaded photos from Spaces / its CDN.
        'Content-Security-Policy' => implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action {$formAction}",
            "frame-ancestors 'self'",
            "script-src 'self' https://www.youtube.com" . ($analytics ? ' https://www.googletagmanager.com' : ''),
            "style-src 'self' https://fonts.googleapis.com",
            "style-src-attr 'unsafe-inline'",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self'",
            // 'self': the dashboard shows a live preview of the page in an iframe
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com",
            "connect-src {$connectSrc}",
        ]),
    ],

    'cors' => [
        'allowed_origins' => [],           // e.g. ['https://app.example.com']; empty disables CORS
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'supports_credentials' => false,
        'max_age' => 600,
    ],

    // Argon2id cost for passwords and page answers. PHP's default (64 MiB per hash) lets a handful of parallel sign-ins
    // exhaust a small container; 19 MiB, 2 passes, 1 lane is OWASP's recommended Argon2id minimum. Existing hashes are
    // upgraded on the next successful sign-in or unlock.
    'hashing' => [
        'memory_cost' => (int) env('HASH_MEMORY_KIB', 19456),
        'time_cost' => (int) env('HASH_TIME_COST', 2),
        'threads' => 1,
    ],
    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'issuer' => env('APP_URL'),
        'leeway' => 10,
    ],
];
