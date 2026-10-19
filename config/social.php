<?php

declare(strict_types=1);

// Sign in / sign up with Google or Facebook. A provider is on when both its id and secret are set; with neither set the
// buttons do not appear and the routes answer 404. Server-side OAuth only (no provider JavaScript on the page).
//
// Redirect URIs to register with the provider (the product's own host, APP_URL):
//   Google:   <APP_URL>/auth/google/callback
//   Facebook: <APP_URL>/auth/facebook/callback
return [
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
