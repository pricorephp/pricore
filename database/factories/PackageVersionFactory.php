<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PackageVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PackageVersion>
 */
class PackageVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = PackageVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $major = fake()->numberBetween(0, 5);
        $minor = fake()->numberBetween(0, 20);
        $patch = fake()->numberBetween(0, 50);
        $version = "{$major}.{$minor}.{$patch}";

        return [
            'package_uuid' => Package::factory(),
            'version' => $version,
            'normalized_version' => "{$major}.{$minor}.{$patch}.0",
            'composer_json' => $this->generateComposerJson($version),
            'source_url' => fake()->url(),
            'source_reference' => fake()->sha1(),
            'dist_url' => fake()->url().'/archive/'.$version.'.zip',
            'released_at' => fake()->dateTimeBetween('-2 years', 'now'),
        ];
    }

    /**
     * Generate realistic composer.json metadata.
     *
     * @return array<string, mixed>
     */
    protected function generateComposerJson(string $version): array
    {
        return [
            'name' => fake()->userName().'/'.fake()->word(),
            'description' => fake()->sentence(),
            'version' => $version,
            'type' => fake()->randomElement(['library', 'framework', 'bundle']),
            'license' => fake()->randomElement(['MIT', 'Apache-2.0', 'GPL-3.0', 'BSD-3-Clause']),
            'authors' => [
                [
                    'name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                ],
            ],
            'require' => [
                'php' => '^8.1 || ^8.2 || ^8.3',
            ],
            'autoload' => [
                'psr-4' => [
                    'Vendor\\Package\\' => 'src/',
                ],
            ],
        ];
    }

    /**
     * Indicate that the version belongs to a specific package.
     */
    public function forPackage(Package $package): static
    {
        return $this->state(fn (array $attributes) => [
            'package_uuid' => $package->uuid,
        ]);
    }

    /**
     * Indicate that the version is a development branch.
     */
    public function devBranch(string $branch = 'main'): static
    {
        return $this->state(function (array $attributes) use ($branch) {
            $version = "dev-{$branch}";

            return [
                'version' => $version,
                'normalized_version' => '9999999-dev',
                'composer_json' => array_merge($attributes['composer_json'] ?? [], [
                    'version' => $version,
                ]),
            ];
        });
    }

    /**
     * Indicate that the version is a beta release.
     */
    public function beta(): static
    {
        return $this->state(function (array $attributes) {
            $major = fake()->numberBetween(0, 5);
            $minor = fake()->numberBetween(0, 20);
            $patch = fake()->numberBetween(0, 50);
            $beta = fake()->numberBetween(1, 5);
            $version = "{$major}.{$minor}.{$patch}-beta.{$beta}";

            return [
                'version' => $version,
                'normalized_version' => "{$major}.{$minor}.{$patch}.0-beta{$beta}",
                'composer_json' => array_merge($attributes['composer_json'] ?? [], [
                    'version' => $version,
                ]),
            ];
        });
    }
}
