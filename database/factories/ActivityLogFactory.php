<?php

namespace Database\Factories;

use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_uuid' => Organization::factory(),
            'actor_uuid' => User::factory(),
            'type' => fake()->randomElement(ActivityType::cases()),
            'properties' => [],
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_uuid' => null,
        ]);
    }

    public function type(ActivityType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
