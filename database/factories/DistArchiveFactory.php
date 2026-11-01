<?php

namespace Database\Factories;

use App\Models\DistArchive;
use App\Models\PackageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DistArchive>
 */
class DistArchiveFactory extends Factory
{
    protected $model = DistArchive::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $packageVersion = PackageVersion::factory();

        return [
            'package_uuid' => fn (array $attributes) => PackageVersion::find($attributes['package_version_uuid'])?->package_uuid,
            'package_version_uuid' => $packageVersion,
            'source_reference' => fake()->sha1(),
            'path' => fake()->slug().'.zip',
            'shasum' => fake()->sha1(),
            'size' => fake()->numberBetween(1024, 1024 * 1024),
            'detached_at' => null,
        ];
    }

    public function forPackageVersion(PackageVersion $packageVersion): static
    {
        return $this->state(fn (array $attributes) => [
            'package_uuid' => $packageVersion->package_uuid,
            'package_version_uuid' => $packageVersion->uuid,
            'source_reference' => $packageVersion->source_reference ?? fake()->sha1(),
        ]);
    }

    public function detached(?Carbon $detachedAt = null): static
    {
        return $this->state(fn (array $attributes) => [
            'detached_at' => $detachedAt ?? now(),
        ]);
    }
}
