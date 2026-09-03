<?php

return [

    'mirrors' => [
        'allowed_private_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', (string) env('MIRROR_ALLOWED_PRIVATE_HOSTS', '')),
        ))),
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
