<?php

namespace App\Domains\Security\Actions;

use App\Domains\Security\Contracts\Enums\AdvisoryMatchType;
use App\Models\PackageVersion;
use App\Models\SecurityAdvisory;
use App\Models\SecurityAdvisoryMatch;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Log;

class MatchAdvisoriesForPackageVersionAction
{
    /**
     * Match advisories against a specific package version.
     *
     * @return int Number of new matches created
     */
    public function handle(PackageVersion $packageVersion): int
    {
        $package = $packageVersion->package;
        $matchesCreated = 0;

        // Direct matching — the package itself is vulnerable
        $matchesCreated += $this->matchDirect($packageVersion, $package->name);

        // Dependency matching — a dependency of this package is vulnerable
        $matchesCreated += $this->matchDependencies($packageVersion);

        return $matchesCreated;
    }

    protected function matchDirect(PackageVersion $packageVersion, string $packageName): int
    {
        $advisories = SecurityAdvisory::where('package_name', $packageName)->get();
        $matchesCreated = 0;

        $existingMatchIds = $packageVersion->advisoryMatches()
            ->where('match_type', AdvisoryMatchType::Direct)
            ->pluck('security_advisory_uuid')
            ->all();

        $currentMatchIds = [];

        foreach ($advisories as $advisory) {
            if (! $this->versionSatisfiesConstraint($packageVersion->normalized_version, $advisory->affected_versions)) {
                continue;
            }

            $currentMatchIds[] = $advisory->uuid;

            if (in_array($advisory->uuid, $existingMatchIds)) {
                continue;
            }

            SecurityAdvisoryMatch::create([
                'security_advisory_uuid' => $advisory->uuid,
                'package_version_uuid' => $packageVersion->uuid,
                'match_type' => AdvisoryMatchType::Direct,
                'dependency_name' => null,
            ]);

            $matchesCreated++;
        }

        // Remove stale direct matches
        $packageVersion->advisoryMatches()
            ->where('match_type', AdvisoryMatchType::Direct)
            ->whereNotIn('security_advisory_uuid', $currentMatchIds)
            ->delete();

        return $matchesCreated;
    }

    protected function matchDependencies(PackageVersion $packageVersion): int
    {
        $composerJson = $packageVersion->composer_json ?? [];
        $require = $composerJson['require'] ?? [];
        $requireDev = $composerJson['require-dev'] ?? [];

        $allDependencies = array_merge($require, $requireDev);

        // Filter out php and ext-* entries, keep name => constraint mapping
        $dependencies = array_filter(
            array: $allDependencies,
            callback: fn (mixed $constraint, string|int $name) => is_string($name)
                && ! str_starts_with($name, 'php')
                && ! str_starts_with($name, 'ext-')
                && str_contains($name, '/'),
            mode: ARRAY_FILTER_USE_BOTH,
        );

        if (empty($dependencies)) {
            // Clean up any stale dependency matches
            $packageVersion->advisoryMatches()
                ->where('match_type', AdvisoryMatchType::Dependency)
                ->delete();

            return 0;
        }

        $advisories = SecurityAdvisory::whereIn('package_name', array_keys($dependencies))->get();
        $matchesCreated = 0;

        $existingMatches = $packageVersion->advisoryMatches()
            ->where('match_type', AdvisoryMatchType::Dependency)
            ->get()
            ->keyBy(fn (SecurityAdvisoryMatch $match) => "{$match->security_advisory_uuid}:{$match->dependency_name}");

        $currentMatchKeys = [];

        foreach ($advisories as $advisory) {
            $depName = $advisory->package_name;
            $requiredConstraint = $dependencies[$depName] ?? null;

            // Skip if the required constraint can't overlap with the affected versions
            if ($requiredConstraint && ! $this->constraintsIntersect($requiredConstraint, $advisory->affected_versions)) {
                continue;
            }

            $matchKey = "{$advisory->uuid}:{$depName}";
            $currentMatchKeys[] = $matchKey;

            if ($existingMatches->has($matchKey)) {
                continue;
            }

            SecurityAdvisoryMatch::create([
                'security_advisory_uuid' => $advisory->uuid,
                'package_version_uuid' => $packageVersion->uuid,
                'match_type' => AdvisoryMatchType::Dependency,
                'dependency_name' => $depName,
            ]);

            $matchesCreated++;
        }

        // Remove stale dependency matches
        $staleMatches = $existingMatches->reject(fn ($match, $key) => in_array($key, $currentMatchKeys));

        if ($staleMatches->isNotEmpty()) {
            SecurityAdvisoryMatch::whereIn('uuid', $staleMatches->pluck('uuid'))->delete();
        }

        return $matchesCreated;
    }

    protected function versionSatisfiesConstraint(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies($version, $constraint);
        } catch (\Throwable $e) {
            Log::debug('Failed to check version constraint', [
                'version' => $version,
                'constraint' => $constraint,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check whether two version constraints could overlap.
     * E.g. "^11.0" and ">=5.5,<7.23.2" do not intersect.
     */
    protected function constraintsIntersect(string $requiredConstraint, string $affectedVersions): bool
    {
        try {
            $parser = new VersionParser;
            $required = $parser->parseConstraints($requiredConstraint);
            $affected = $parser->parseConstraints($affectedVersions);

            return $required->matches($affected);
        } catch (\Throwable $e) {
            // If we can't parse, err on the side of caution and flag it
            return true;
        }
    }
}
