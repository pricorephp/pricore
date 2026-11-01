<?php

namespace Database\Factories;

use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccessToken>
 */
class AccessTokenFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = AccessToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_uuid' => Organization::factory(),
            'user_uuid' => null,
            'name' => fake()->words(2, true).' Token',
            'token_hash' => hash('sha256', Str::random(64)),
            'scopes' => null,
            'last_used_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('now', '+2 years'),
        ];
    }

    /**
     * Indicate that the token belongs to a specific organization.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_uuid' => $organization->uuid,
            'user_uuid' => null,
        ]);
    }

    /**
     * Indicate that the token belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_uuid' => null,
            'user_uuid' => $user->uuid,
        ]);
    }

    /**
     * Indicate that the token has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    /**
     * Indicate that the token never expires.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the token has specific scopes.
     *
     * @param  array<int, TokenScope|string>  $scopes
     */
    public function withScopes(array $scopes): static
    {
        return $this->state(fn (array $attributes) => [
            'scopes' => array_map(
                fn (TokenScope|string $scope) => $scope instanceof TokenScope ? $scope->value : $scope,
                $scopes,
            ),
        ]);
    }

    /**
     * Indicate that the token is limited to Composer registry access.
     */
    public function composerOnly(): static
    {
        return $this->withScopes([TokenScope::Composer]);
    }

    /**
     * Indicate that the token was recently used.
     */
    public function recentlyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create a token with a known plain text token for testing.
     */
    public function withPlainToken(string $plainToken): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => hash('sha256', $plainToken),
        ]);
    }
}
