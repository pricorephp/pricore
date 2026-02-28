<?php

namespace Database\Factories;

use App\Domains\Repository\Contracts\Enums\SyncStatus;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RepositorySyncLog>
 */
class RepositorySyncLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 month', 'now');
        $completedAt = fake()->optional(0.9)->dateTimeBetween($startedAt, 'now');

        return [
            'repository_uuid' => Repository::factory(),
            'status' => fake()->randomElement([SyncStatus::Pending, SyncStatus::Success, SyncStatus::Failed]),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'error_message' => null,
            'details' => null,
            'versions_added' => fake()->numberBetween(0, 10),
            'versions_updated' => fake()->numberBetween(0, 5),
            'versions_removed' => fake()->numberBetween(0, 3),
        ];
    }

    /**
     * Indicate that the sync is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Pending,
            'completed_at' => null,
            'error_message' => null,
            'versions_added' => 0,
            'versions_updated' => 0,
            'versions_removed' => 0,
        ]);
    }

    /**
     * Indicate that the sync was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Success,
            'completed_at' => fake()->dateTimeBetween($attributes['started_at'], 'now'),
            'error_message' => null,
            'details' => [
                'tags_processed' => fake()->numberBetween(1, 20),
                'branches_processed' => fake()->numberBetween(1, 5),
            ],
        ]);
    }

    /**
     * Indicate that the sync failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::Failed,
            'completed_at' => fake()->dateTimeBetween($attributes['started_at'], 'now'),
            'error_message' => fake()->randomElement([
                'Could not connect to repository',
                'Invalid composer.json file',
                'Repository not found',
                'Authentication failed',
                'Rate limit exceeded',
            ]),
            'versions_added' => 0,
            'versions_updated' => 0,
            'versions_removed' => 0,
        ]);
    }
}
