<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use Composer\Semver\Comparator;
use Spatie\LaravelData\DataCollection;

class CollectRefsAction
{
    /**
     * Collect all tags and branches from the repository.
     * Tags are sorted by semantic version (newest first).
     * Branches prioritize main/master/develop, then alphabetically.
     */
    public function handle(GitProviderInterface $provider): RefsCollectionData
    {
        $tags = $provider->getTags();
        $branches = $provider->getBranches();

        $sortedTags = $this->sortTagsByVersion($tags);
        $sortedBranches = $this->sortBranchesByPriority($branches);

        // Process tags first (newest first), then branches
        $all = array_merge($sortedTags, $sortedBranches);

        return new RefsCollectionData(
            tags: new DataCollection(
                RefData::class,
                array_map(
                    /** @param array<string, mixed> $ref */
                    fn (array $ref) => RefData::from($ref),
                    $sortedTags
                )
            ),
            branches: new DataCollection(
                RefData::class,
                array_map(
                    /** @param array<string, mixed> $ref */
                    fn (array $ref) => RefData::from($ref),
                    $sortedBranches
                )
            ),
            all: new DataCollection(
                RefData::class,
                array_map(
                    /** @param array<string, mixed> $ref */
                    fn (array $ref) => RefData::from($ref),
                    $all
                )
            ),
        );
    }

    /**
     * Sort tags by semantic version, newest first.
     *
     * @param  array<int, array{name: string, commit: string}>  $tags
     * @return array<int, array{name: string, commit: string}>
     */
    protected function sortTagsByVersion(array $tags): array
    {
        usort($tags, function (array $a, array $b): int {
            $versionA = $this->normalizeVersion($a['name']);
            $versionB = $this->normalizeVersion($b['name']);

            try {
                if (Comparator::equalTo($versionA, $versionB)) {
                    return 0;
                }

                return Comparator::greaterThan($versionA, $versionB) ? -1 : 1;
            } catch (\Throwable) {
                // Fall back to string comparison if semver parsing fails
                return version_compare($versionB, $versionA);
            }
        });

        return $tags;
    }

    /**
     * Sort branches by priority (main/master/develop first).
     *
     * @param  array<int, array{name: string, commit: string}>  $branches
     * @return array<int, array{name: string, commit: string}>
     */
    protected function sortBranchesByPriority(array $branches): array
    {
        $priority = ['main' => 0, 'master' => 1, 'develop' => 2];

        usort($branches, static function (array $a, array $b) use ($priority): int {
            $priorityA = $priority[$a['name']] ?? 999;
            $priorityB = $priority[$b['name']] ?? 999;

            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return $a['name'] <=> $b['name'];
        });

        return $branches;
    }

    /**
     * Normalize version string for comparison.
     */
    protected function normalizeVersion(string $version): string
    {
        // Remove common prefixes like 'v' or 'release-'
        return preg_replace('/^(v|release[-_]?)/i', '', $version) ?? $version;
    }
}
