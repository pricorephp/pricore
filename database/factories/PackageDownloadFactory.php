<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageDownload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PackageDownload>
 */
class PackageDownloadFactory extends Factory
{
    protected $model = PackageDownload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vendor = Str::slug(fake()->unique()->userName());
        $packageName = Str::slug(fake()->words(2, true));

        return [
            'organization_uuid' => Organization::factory(),
            'package_uuid' => null,
            'package_name' => "{$vendor}/{$packageName}",
            'version' => fake()->semver(),
            'downloaded_at' => now(),
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_uuid' => $organization->uuid,
        ]);
    }

    public function forPackage(Package $package): static
    {
        return $this->state(fn (array $attributes) => [
            'package_uuid' => $package->uuid,
            'package_name' => $package->name,
        ]);
    }
}
