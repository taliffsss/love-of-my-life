<?php

declare(strict_types=1);

return [
    // Override or disable (null) any default security header.
    'headers' => [
        // Fonts come from Google Fonts, the song from YouTube's embed, and uploaded photos from Spaces / its CDN.
        'Content-Security-Policy' => implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "script-src 'self' https://www.youtube.com",
            "style-src 'self' https://fonts.googleapis.com",
            "style-src-attr 'unsafe-inline'",
            "font-src https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "media-src 'self'",
            // 'self': the dashboard shows a live preview of the page in an iframe
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com",
            "connect-src 'self'",
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
