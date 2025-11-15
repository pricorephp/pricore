<?php

namespace Database\Factories;

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Repository>
 */
class RepositoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Repository::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement(['github', 'gitlab', 'bitbucket', 'git']);
        $owner = fake()->userName();
        $repoName = fake()->words(2, true);

        return [
            'organization_uuid' => Organization::factory(),
            'name' => $repoName,
            'provider' => $provider,
            'repo_identifier' => $this->generateRepoIdentifier($provider, $owner, $repoName),
            'default_branch' => fake()->randomElement(['main', 'master', 'develop']),
            'last_synced_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'sync_status' => fake()->randomElement([RepositorySyncStatus::Ok, RepositorySyncStatus::Failed, RepositorySyncStatus::Pending]),
        ];
    }

    /**
     * Generate a repository identifier based on the provider.
     */
    protected function generateRepoIdentifier(string $provider, string $owner, string $repoName): string
    {
        return match ($provider) {
            'github' => "{$owner}/{$repoName}",
            'gitlab' => "{$owner}/{$repoName}",
            'bitbucket' => "{$owner}/{$repoName}",
            'git' => fake()->url().'.git',
        };
    }

    /**
     * Indicate that the repository belongs to a specific organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_uuid' => $organization->uuid,
        ]);
    }

    /**
     * Indicate that the repository is a GitHub repository.
     */
    public function github(): static
    {
        return $this->state(function (array $attributes) {
            $owner = fake()->userName();
            $repoName = fake()->words(2, true);

            return [
                'provider' => 'github',
                'repo_identifier' => "{$owner}/{$repoName}",
            ];
        });
    }

    /**
     * Indicate that the repository is a GitLab repository.
     */
    public function gitlab(): static
    {
        return $this->state(function (array $attributes) {
            $owner = fake()->userName();
            $repoName = fake()->words(2, true);

            return [
                'provider' => 'gitlab',
                'repo_identifier' => "{$owner}/{$repoName}",
            ];
        });
    }

    /**
     * Indicate that the repository sync status is OK.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => now(),
            'sync_status' => RepositorySyncStatus::Ok,
        ]);
    }

    /**
     * Indicate that the repository sync has failed.
     */
    public function syncFailed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_synced_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'sync_status' => RepositorySyncStatus::Failed,
        ]);
    }
}
