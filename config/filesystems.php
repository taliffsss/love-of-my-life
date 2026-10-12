<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        // private files: never reachable from the web
        'local' => ['driver' => 'local', 'root' => 'storage/app'],
        // web-accessible uploads (symlink or serve public/storage): URL is built from `url`
        'public' => ['driver' => 'local', 'root' => 'public/storage', 'url' => '/storage'],
        // DigitalOcean Spaces (S3-compatible). Registered in App\Providers\AppServiceProvider once the keys are set.
        'spaces' => [
            'driver' => 'spaces',
            'endpoint' => env('SPACES_ENDPOINT_URL'),
            'region' => env('SPACES_REGION', 'nyc3'),
            'bucket' => env('SPACES_BUCKET'),
            'key' => env('SPACES_ACCESS_KEY'),
            'secret' => env('SPACES_SECRET_KEY'),
            'folder' => env('SPACES_FOLDER', ''),
            'cdn_url' => env('SPACES_CDN_URL'),
        ],
    ],
];
