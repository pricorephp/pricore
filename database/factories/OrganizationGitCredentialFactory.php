<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationGitCredential>
 */
class OrganizationGitCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_uuid' => Organization::factory(),
            'provider' => fake()->randomElement(['github', 'gitlab', 'bitbucket', 'git']),
            'credentials' => [
                'token' => fake()->sha256(),
            ],
        ];
    }

    /**
     * Indicate that the credential is for GitHub.
     */
    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'github',
            'credentials' => [
                'token' => 'ghp_'.fake()->sha256(),
            ],
        ]);
    }

    /**
     * Indicate that the credential is for GitLab.
     */
    public function gitlab(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'gitlab',
            'credentials' => [
                'token' => 'glpat-'.fake()->sha256(),
                'url' => fake()->optional()->url(),
            ],
        ]);
    }

    /**
     * Indicate that the credential is for Bitbucket.
     */
    public function bitbucket(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'bitbucket',
            'credentials' => [
                'username' => fake()->userName(),
                'app_password' => fake()->sha256(),
            ],
        ]);
    }

    /**
     * Indicate that the credential is for generic Git.
     */
    public function git(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'git',
            'credentials' => [
                'ssh_key' => fake()->text(500),
            ],
        ]);
    }
}
