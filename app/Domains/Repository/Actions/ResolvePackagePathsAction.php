<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Services\PackagePaths\PackagePathPattern;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

class ResolvePackagePathsAction
{
    /**
     * Directories holding a composer.json to sync at $ref, relative to the
     * repository root ('' for the root itself). Without configured paths the
     * repository is the classic single package at the root; with them, the
     * root takes part only when listed explicitly as ".".
     *
     * @return array<int, string>
     */
    public function handle(GitProviderInterface $provider, Repository $repository, string $ref): array
    {
        $patterns = $repository->package_paths ?? [];

        if ($patterns === []) {
            return [''];
        }

        $paths = [];

        foreach ($patterns as $pattern) {
            $pattern = PackagePathPattern::normalize((string) $pattern);

            if (! PackagePathPattern::isValid($pattern)) {
                Log::warning('Ignoring invalid package path pattern', [
                    'repository' => $repository->name,
                    'pattern' => $pattern,
                ]);

                continue;
            }

            if ($pattern === PackagePathPattern::ROOT) {
                $paths[] = '';

                continue;
            }

            if (! PackagePathPattern::isWildcard($pattern)) {
                $paths[] = $pattern;

                continue;
            }

            $parent = PackagePathPattern::wildcardParent($pattern);

            foreach ($provider->listDirectory($ref, $parent) as $entry) {
                if ($entry['type'] !== 'dir' || str_starts_with($entry['name'], '.') || ! PackagePathPattern::isValidSegment($entry['name'])) {
                    continue;
                }

                $paths[] = $parent === '' ? $entry['name'] : "{$parent}/{$entry['name']}";
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }
}
