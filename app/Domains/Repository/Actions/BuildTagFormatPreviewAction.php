<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\TagFormatPreviewData;
use App\Domains\Repository\Contracts\Enums\TagRelease;
use App\Domains\Repository\Services\TagFormats\TagFormat;
use App\Models\Package;
use App\Models\Repository;

class BuildTagFormatPreviewAction
{
    /**
     * @param  array<int, string>  $tags
     * @return array<int, TagFormatPreviewData>
     */
    public function handle(Repository $repository, TagFormat $format, array $tags): array
    {
        $packageNames = $repository->packages()
            ->get(['name', 'source_path'])
            ->mapWithKeys(fn (Package $package) => [(string) $format->keyFor($package->source_path, $package->name) => $package->name])
            ->forget('');

        return array_map(function (string $tag) use ($format, $packageNames): TagFormatPreviewData {
            $target = TagFormat::target($format, new RefData($tag, '', isTag: true));

            return match (true) {
                $target === null => new TagFormatPreviewData($tag, TagRelease::Ignored, version: null, package: null, packageKnown: false),
                $target->isShared() => new TagFormatPreviewData($tag, TagRelease::Shared, $target->version, package: null, packageKnown: false),
                default => new TagFormatPreviewData(
                    tag: $tag,
                    release: TagRelease::Package,
                    version: $target->version,
                    package: $packageNames->get((string) $target->packageKey, $target->packageKey),
                    packageKnown: $packageNames->has((string) $target->packageKey),
                ),
            };
        }, $tags);
    }
}
