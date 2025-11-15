<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Services\GitProviders\Contracts\GitProviderInterface;
use Spatie\LaravelData\DataCollection;

class CollectRefsAction
{
    /**
     * Collect all tags and branches from the repository.
     */
    public function handle(GitProviderInterface $provider): RefsCollectionData
    {
        $tags = $provider->getTags();
        $branches = $provider->getBranches();

        $all = array_merge($tags, $branches);

        return new RefsCollectionData(
            tags: new DataCollection(
                RefData::class,
                array_map(
                    /** @param array<string, mixed> $ref */
                    fn (array $ref) => RefData::from($ref),
                    $tags
                )
            ),
            branches: new DataCollection(
                RefData::class,
                array_map(
                    /** @param array<string, mixed> $ref */
                    fn (array $ref) => RefData::from($ref),
                    $branches
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
}
