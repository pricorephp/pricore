<?php

namespace App\Domains\Composer\Actions;

use App\Domains\Mirror\Contracts\Enums\MirrorSyncMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class ResolveOnDemandMirrorsToggledAtAction
{
    private const string CACHE_KEY = 'composer:on-demand-mirrors-state';

    /**
     * When on-demand mirrors were last turned on or off.
     *
     * Turning them on or off adds or drops the package list in packages.json,
     * which no stored timestamp reflects. Counting this moment into
     * Last-Modified makes clients refetch instead of keeping the old list
     * after an If-Modified-Since check. A cleared cache only costs every
     * client one refetch.
     */
    public function handle(): CarbonImmutable
    {
        $enabled = MirrorSyncMode::onDemandEnabled();

        /** @var array{enabled: bool, since: int}|null $state */
        $state = Cache::get(self::CACHE_KEY);

        if ($state === null || $state['enabled'] !== $enabled) {
            $state = ['enabled' => $enabled, 'since' => now()->getTimestamp()];

            Cache::forever(self::CACHE_KEY, $state);
        }

        return CarbonImmutable::createFromTimestamp($state['since']);
    }
}
