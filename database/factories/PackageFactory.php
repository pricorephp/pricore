<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Package;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Package::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vendor = Str::slug(fake()->unique()->userName());
        $packageName = Str::slug(fake()->words(2, true));

        return [
            'organization_uuid' => Organization::factory(),
            'repository_uuid' => Repository::factory(),
            'name' => "{$vendor}/{$packageName}",
            'description' => fake()->optional(0.8)->sentence(),
            'type' => fake()->randomElement(['library', 'framework', 'bundle', 'plugin', 'component', 'tool']),
            'visibility' => 'private',
            'is_proxy' => false,
        ];
    }

    /**
     * Indicate that the package belongs to a specific organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_uuid' => $organization->uuid,
        ]);
    }

    /**
     * Indicate that the package is linked to a specific repository.
     */
    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes) => [
            'repository_uuid' => $repository->uuid,
        ]);
    }

    /**
     * Indicate that the package has no linked repository.
     */
    public function withoutRepository(): static
    {
        return $this->state(fn (array $attributes) => [
            'repository_uuid' => null,
        ]);
    }

    /**
     * Indicate that the package is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the package is a proxy for an external package.
     */
    public function proxy(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_proxy' => true,
        ]);
    }
}
