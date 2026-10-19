<?php

declare(strict_types=1);

// Sign in / sign up with Google or Facebook. A provider is on when both its id and secret are set; with neither set the
// buttons do not appear and the routes answer 404. Server-side OAuth only (no provider JavaScript on the page).
//
// Redirect URIs to register with the provider (the product's own host, APP_URL):
//   Google:   <APP_URL>/auth/google/callback
//   Facebook: <APP_URL>/auth/facebook/callback
return [
    // Show both buttons even before the keys are set, so the screens can be seen and designed (a click says what is missing).
    // Always on for APP_ENV local, testing, development and staging; elsewhere only with SOCIAL_LOGIN_PREVIEW=true.
    'preview' => in_array((string) env('APP_ENV', 'production'), ['local', 'testing', 'development', 'staging'], true)
        || filter_var(env('SOCIAL_LOGIN_PREVIEW', false), FILTER_VALIDATE_BOOLEAN),

    'google' => [
        'client_id' => (string) env('GOOGLE_CLIENT_ID', ''),
        'client_secret' => (string) env('GOOGLE_CLIENT_SECRET', ''),
    ],
    'facebook' => [
        'client_id' => (string) env('FACEBOOK_APP_ID', ''),
        'client_secret' => (string) env('FACEBOOK_APP_SECRET', ''),
        'graph_version' => (string) env('FACEBOOK_GRAPH_API_VERSION', 'v26.0'),
    ],
];
