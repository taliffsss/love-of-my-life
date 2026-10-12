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
            "frame-src https://www.youtube.com https://www.youtube-nocookie.com",
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

    'jwt' => [
        'secret' => env('JWT_SECRET', ''),
        'issuer' => env('APP_URL'),
        'leeway' => 10,
    ],
];
