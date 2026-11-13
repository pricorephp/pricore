<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IP addresses or CIDR ranges of the reverse proxies in
    | front of Pricore, or "*" to trust every proxy. Laravel's TrustProxies
    | middleware reads this value, so it keeps working once the configuration
    | is cached and the .env file is no longer loaded.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', ''),

];
