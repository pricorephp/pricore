<?php

namespace Database\Factories;

use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\SecurityAdvisory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<SecurityAdvisory>
 */
class SecurityAdvisoryFactory extends Factory
{
    /**
     * @var class-string<Model>
     */
    protected $model = SecurityAdvisory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vendor = fake()->userName();
        $package = fake()->word();

        return [
            'advisory_id' => 'PKSA-'.fake()->unique()->randomNumber(6),
            'package_name' => "{$vendor}/{$package}",
            'title' => fake()->sentence(),
            'link' => fake()->url(),
            'cve' => 'CVE-'.fake()->year().'-'.fake()->randomNumber(5, true),
            'affected_versions' => '>='.fake()->numberBetween(1, 3).'.0.0,<'.fake()->numberBetween(1, 3).'.'.fake()->numberBetween(1, 9).'.'.fake()->numberBetween(1, 9),
            'severity' => fake()->randomElement(AdvisorySeverity::cases()),
            'sources' => [
                ['name' => 'GitHub', 'remoteId' => 'GHSA-'.fake()->lexify('????-????-????')],
            ],
            'reported_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'composer_repository' => 'https://packagist.org',
        ];
    }

    public function forPackage(string $packageName): static
    {
        return $this->state(fn () => [
            'package_name' => $packageName,
        ]);
    }

    public function severity(AdvisorySeverity $severity): static
    {
        return $this->state(fn () => [
            'severity' => $severity,
        ]);
    }

    public function affectedVersions(string $constraint): static
    {
        return $this->state(fn () => [
            'affected_versions' => $constraint,
        ]);
    }
}
