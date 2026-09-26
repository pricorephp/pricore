<?php

namespace Database\Factories;

use App\Models\Repository;
use App\Models\RepositoryView;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositoryView>
 */
class RepositoryViewFactory extends Factory
{
    protected $model = RepositoryView::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'repository_uuid' => Repository::factory(),
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

    public function forRepository(Repository $repository): static
    {
        return $this->state(fn (array $attributes) => [
            'repository_uuid' => $repository->uuid,
        ]);
    }
}
