<?php

namespace App\Domains\Composer\Contracts\Data;

use App\Models\PackageVersion;
use Spatie\LaravelData\Data;

class VersionMetadataData extends Data
{
    /**
     * @param  array<string, mixed>  $composerJson
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $versionNormalized,
        public array $composerJson,
        public ?SourceData $source = null,
        public ?SourceData $dist = null,
        public ?string $time = null,
    ) {}

    public static function fromPackageVersion(PackageVersion $version): self
    {
        $source = null;
        if ($version->source_url && $version->source_reference) {
            $source = new SourceData(
                type: 'git',
                url: $version->source_url,
                reference: $version->source_reference,
            );
        }

        $dist = null;
        if ($version->dist_url) {
            $dist = new SourceData(
                type: 'zip',
                url: $version->dist_url,
                reference: $version->source_reference,
            );
        }

        return new self(
            name: $version->package->name,
            version: $version->version,
            versionNormalized: $version->normalized_version,
            composerJson: $version->composer_json,
            source: $source,
            dist: $dist,
            time: $version->released_at?->toIso8601String(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $metadata = $this->composerJson;

        $metadata['name'] = $this->name;
        $metadata['version'] = $this->version;
        $metadata['version_normalized'] = $this->versionNormalized;

        if ($this->source) {
            $metadata['source'] = $this->source->toArray();
        }

        if ($this->dist) {
            $metadata['dist'] = $this->dist->toArray();
        }

        if ($this->time) {
            $metadata['time'] = $this->time;
        }

        return $metadata;
    }
}
