<?php

namespace Database\Factories;

use App\Domains\Mirror\Contracts\Enums\MirrorAuthType;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Mirror;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Mirror>
 */
class MirrorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Mirror::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_uuid' => Organization::factory(),
            'name' => fake()->company().' Registry',
            'url' => fake()->url(),
            'auth_type' => MirrorAuthType::None,
            'auth_credentials' => null,
            'mirror_dist' => true,
            'sync_status' => null,
            'last_synced_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_uuid' => $organization->uuid,
        ]);
    }

    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => RepositorySyncStatus::Ok,
            'last_synced_at' => now(),
        ]);
    }

    public function syncFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_status' => RepositorySyncStatus::Failed,
            'last_synced_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function withBasicAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'auth_type' => MirrorAuthType::Basic,
            'auth_credentials' => [
                'username' => fake()->userName(),
                'password' => fake()->password(),
            ],
        ]);
    }

    public function withBearerAuth(): static
    {
        return $this->state(fn (array $attributes) => [
            'auth_type' => MirrorAuthType::Bearer,
            'auth_credentials' => [
                'token' => fake()->sha256(),
            ],
        ]);
    }

    public function withoutDist(): static
    {
        return $this->state(fn (array $attributes) => [
            'mirror_dist' => false,
        ]);
    }
}
