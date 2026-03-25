<?php

namespace App\Domains\Security\Actions;

use App\Models\Package;

class MatchAdvisoriesForPackageAction
{
    public function __construct(
        protected MatchAdvisoriesForPackageVersionAction $matchAdvisoriesForPackageVersionAction,
    ) {}

    /**
     * Scan all versions of a package for advisory matches.
     *
     * @return int Number of new matches created
     */
    public function handle(Package $package): int
    {
        $matchesCreated = 0;

        $package->versions->each(function ($packageVersion) use (&$matchesCreated) {
            $matchesCreated += $this->matchAdvisoriesForPackageVersionAction->handle($packageVersion);
        });

        return $matchesCreated;
    }
}
