<?php

return [

    'dist' => [
        'enabled' => env('DIST_ENABLED', true),
        'disk' => env('DIST_DISK', 'local'),
        'signed_url_expiry' => env('DIST_SIGNED_URL_EXPIRY', 30), // minutes, for S3
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Read through config rather than env() directly, so the value survives
    | `php artisan config:cache` (which stops .env from being loaded at all).
    | Use "*" to trust every proxy, or a comma-separated list of addresses.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES', ''),

    /*
    |--------------------------------------------------------------------------
    | Outbound Requests
    |--------------------------------------------------------------------------
    |
    | Pricore fetches repositories, registry metadata and dist archives from
    | URLs supplied by users. These ranges are refused so those fetches cannot
    | be aimed at the host itself, the private network, or a cloud metadata
    | endpoint. Self-hosters running an internal Git server or registry should
    | list its hostname in OUTBOUND_ALLOWED_HOSTS to opt it back in.
    |
    */

    'outbound' => [
        'blocked_ranges' => [
            '0.0.0.0/8',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '::1/128',
            'fc00::/7',
            'fe80::/10',
        ],

        'allowed_hosts' => array_values(array_filter(
            array_map('trim', explode(',', (string) env('OUTBOUND_ALLOWED_HOSTS', '')))
        )),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Requests per minute. "composer" is keyed by access token and needs to stay
    | generous — a single `composer update` issues hundreds of parallel requests.
    | "composer_auth" applies to failed authentication only and is keyed by IP.
    | "webhooks" is keyed by repository.
    |
    */

    'rate_limits' => [
        'composer' => (int) env('RATE_LIMIT_COMPOSER', 600),
        'composer_auth' => (int) env('RATE_LIMIT_COMPOSER_AUTH', 30),
        'webhooks' => (int) env('RATE_LIMIT_WEBHOOKS', 60),
    ],

];
