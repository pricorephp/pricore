<?php

namespace App\Domains\Composer\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class ResolveBaseUrlFirstServedAction
{
    /**
     * When the registry was first served under its current base URL.
     *
     * Composer metadata embeds absolute URLs, but Composer revalidates its cache
     * with If-Modified-Since only. Counting this moment into Last-Modified makes
     * clients refetch once after APP_URL changes instead of getting a 304 that
     * keeps the old host. A cleared cache only costs every client one refetch.
     */
    public function handle(): CarbonImmutable
    {
        $timestamp = Cache::rememberForever(
            'composer:base-url-first-served:'.sha1(url('/')),
            fn (): int => now()->getTimestamp(),
        );

        return CarbonImmutable::createFromTimestamp($timestamp);
    }
}
