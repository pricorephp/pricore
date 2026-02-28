<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackageView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageView>
 */
class PackageViewFactory extends Factory
{
    protected $model = PackageView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'package_uuid' => Package::factory(),
            'view_count' => fake()->numberBetween(1, 50),
            'last_viewed_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_uuid' => $user->uuid,
        ]);
    }

    public function forPackage(Package $package): static
    {
        return $this->state(fn (array $attributes) => [
            'package_uuid' => $package->uuid,
        ]);
    }
}
