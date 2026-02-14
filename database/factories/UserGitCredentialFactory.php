<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserGitCredential>
 */
class UserGitCredentialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'provider' => fake()->randomElement(['github', 'gitlab', 'bitbucket', 'git']),
            'credentials' => [
                'token' => fake()->sha256(),
            ],
        ];
    }

    public function github(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'github',
            'credentials' => [
                'token' => 'ghp_'.fake()->sha256(),
            ],
        ]);
    }

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
