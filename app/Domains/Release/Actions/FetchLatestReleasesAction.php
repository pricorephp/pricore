<?php

namespace App\Domains\Release\Actions;

use App\Domains\Release\Contracts\Data\ReleaseData;
use App\Domains\Release\Contracts\Data\ReleaseInfoData;
use Composer\Semver\Comparator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FetchLatestReleasesAction
{
    private const CACHE_KEY = 'pricore.releases';

    private const COOLDOWN_KEY = 'pricore.releases:cooldown';

    private const REPO = 'pricorephp/pricore';

    public function handle(): ?ReleaseInfoData
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof ReleaseInfoData) {
            return $cached;
        }

        if (Cache::has(self::COOLDOWN_KEY)) {
            return null;
        }

        $info = $this->fetch();

        if ($info === null) {
            Cache::put(self::COOLDOWN_KEY, true, now()->addMinutes(15));

            return null;
        }

        Cache::put(self::CACHE_KEY, $info, now()->addDay());

        return $info;
    }

    private function fetch(): ?ReleaseInfoData
    {
        try {
            $response = Http::baseUrl('https://api.github.com')
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent' => 'Pricore',
                ])
                ->timeout(10)
                ->get('/repos/'.self::REPO.'/releases', ['per_page' => 20]);

            if (! $response->successful()) {
                Log::warning('Failed to fetch Pricore releases from GitHub', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            /** @var array<int, array<string, mixed>> $payload */
            $payload = $response->json() ?? [];
        } catch (\Throwable $exception) {
            Log::warning('Exception while fetching Pricore releases from GitHub', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $releases = collect($payload)
            ->reject(fn (array $release) => ($release['draft'] ?? false) || ($release['prerelease'] ?? false))
            ->map(fn (array $release) => $this->mapRelease($release))
            ->values()
            ->all();

        $currentVersion = $this->normalizeVersion(config('app.version'));
        $latestVersion = $releases[0]->version ?? null;

        $isOutdated = false;
        if ($currentVersion !== null && $latestVersion !== null) {
            try {
                $isOutdated = Comparator::greaterThan($latestVersion, $currentVersion);
            } catch (\Throwable) {
                $isOutdated = false;
            }
        }

        return new ReleaseInfoData(
            currentVersion: $currentVersion,
            latestVersion: $latestVersion,
            isOutdated: $isOutdated,
            releases: $releases,
        );
    }

    /**
     * @param  array<string, mixed>  $release
     */
    private function mapRelease(array $release): ReleaseData
    {
        $tagName = (string) ($release['tag_name'] ?? '');
        $name = (string) ($release['name'] ?? $tagName);
        $body = (string) ($release['body'] ?? '');

        return new ReleaseData(
            name: $name !== '' ? $name : $tagName,
            tagName: $tagName,
            version: $this->normalizeVersion($tagName) ?? $tagName,
            htmlUrl: (string) ($release['html_url'] ?? ''),
            publishedAt: $release['published_at'] ?? null,
            bodyHtml: $body === '' ? '' : Str::markdown($body, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        );
    }

    private function normalizeVersion(?string $version): ?string
    {
        if ($version === null || $version === '') {
            return null;
        }

        return preg_replace('/^(v|release[-_]?)/i', '', $version) ?? $version;
    }
}
