<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\RefsCollectionData;
use App\Domains\Repository\Services\TagFormats\TagFormat;
use App\Models\Repository;
use Spatie\LaravelData\DataCollection;

class ClaimSharedVersionsAction
{
    /**
     * Mark on each shared tag the packages that release the same version from a
     * tag of their own. Refs are synced one job at a time and some providers
     * cannot list tags there, so the precedence travels with the ref.
     */
    public function handle(Repository $repository, RefsCollectionData $refs): RefsCollectionData
    {
        $format = TagFormat::forRepository($repository);

        if ($format === null) {
            return $refs;
        }

        $tags = collect($refs->tags->toCollection());

        /** @var array<string, array<int, string>> $claimedByVersion */
        $claimedByVersion = [];

        foreach ($tags as $ref) {
            $target = TagFormat::target($format, $ref);

            if ($target !== null && $target->packageKey !== null) {
                $claimedByVersion[$target->version][] = $target->packageKey;
            }
        }

        if ($claimedByVersion === []) {
            return $refs;
        }

        $tags = $tags->map(function (RefData $ref) use ($format, $claimedByVersion) {
            $target = TagFormat::target($format, $ref);

            if ($target === null || ! $target->isShared() || ! isset($claimedByVersion[$target->version])) {
                return $ref;
            }

            return new RefData($ref->name, $ref->commit, $ref->isTag, array_values(array_unique($claimedByVersion[$target->version])));
        });

        $branches = collect($refs->branches->toCollection());

        return new RefsCollectionData(
            tags: new DataCollection(RefData::class, $tags->all()),
            branches: new DataCollection(RefData::class, $branches->all()),
            all: new DataCollection(RefData::class, $tags->merge($branches)->all()),
        );
    }
}
