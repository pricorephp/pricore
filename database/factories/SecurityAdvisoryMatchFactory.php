<?php

namespace Database\Factories;

use App\Domains\Security\Contracts\Enums\AdvisoryMatchType;
use App\Models\PackageVersion;
use App\Models\SecurityAdvisory;
use App\Models\SecurityAdvisoryMatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<SecurityAdvisoryMatch>
 */
class SecurityAdvisoryMatchFactory extends Factory
{
    /**
     * @var class-string<Model>
     */
    protected $model = SecurityAdvisoryMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'security_advisory_uuid' => SecurityAdvisory::factory(),
            'package_version_uuid' => PackageVersion::factory(),
            'match_type' => AdvisoryMatchType::Direct,
            'dependency_name' => null,
        ];
    }

    public function dependency(string $dependencyName): static
    {
        return $this->state(fn () => [
            'match_type' => AdvisoryMatchType::Dependency,
            'dependency_name' => $dependencyName,
        ]);
    }
}
