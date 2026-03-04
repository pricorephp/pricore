<?php

return [

    'dist' => [
        'enabled' => env('DIST_ENABLED', true),
        'disk' => env('DIST_DISK', 'local'),
        'signed_url_expiry' => env('DIST_SIGNED_URL_EXPIRY', 30), // minutes, for S3
    ],

];
