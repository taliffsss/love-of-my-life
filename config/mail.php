<?php

declare(strict_types=1);

return [
    // log (write to storage/logs, development) | smtp | array (tests)
    // The EMAIL_* names (EMAIL_HOST, EMAIL_PORT, EMAIL_HOST_USER, EMAIL_HOST_PASSWORD) work too, for providers that publish them that way (Resend).
    'default' => env('MAIL_MAILER', env('EMAIL_HOST') ? 'smtp' : 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', env('EMAIL_FROM', 'hello@example.com')),
        'name' => env('MAIL_FROM_NAME', env('APP_NAME', 'NaluzPHP')),
    ],

    'smtp' => [
        'host' => env('MAIL_HOST', env('EMAIL_HOST', '127.0.0.1')),
        'port' => (int) env('MAIL_PORT', env('EMAIL_PORT', 587)),
        // tls (STARTTLS, port 587) | ssl (implicit TLS, port 465) | none. Not set: 465 means ssl, anything else tls.
        'encryption' => env('MAIL_ENCRYPTION') ?: ((int) env('MAIL_PORT', env('EMAIL_PORT', 587)) === 465 ? 'ssl' : 'tls'),
        'username' => env('MAIL_USERNAME', env('EMAIL_HOST_USER')),
        'password' => env('MAIL_PASSWORD', env('EMAIL_HOST_PASSWORD')),
        'timeout' => 10,
    ],
];
