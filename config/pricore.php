<?php

return [

    'mirrors' => [
        'allowed_private_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', (string) env('MIRROR_ALLOWED_PRIVATE_HOSTS', '')),
        ))),
    ],

    'uploads' => [
        // Megabytes. PHP's upload_max_filesize and post_max_size, and any proxy
        // body limit in front of Pricore, must allow at least this much.
        'max_size' => (int) env('ARTIFACT_MAX_SIZE', 64),

        'rate_limit_per_minute' => (int) env('ARTIFACT_UPLOAD_RATE_LIMIT', 30),
    ],

    'dist' => [
        'enabled' => env('DIST_ENABLED', true),
        'disk' => env('DIST_DISK', 'local'),
        'signed_url_expiry' => env('DIST_SIGNED_URL_EXPIRY', 30), // minutes, for S3

        // Days to keep archives a branch has moved past, counted from when they
        // stopped being current. Unset keeps them indefinitely, so lock files
        // pinning older commits stay installable.
        'keep_detached_days' => env('DIST_KEEP_DETACHED_DAYS'),
    ],

];
