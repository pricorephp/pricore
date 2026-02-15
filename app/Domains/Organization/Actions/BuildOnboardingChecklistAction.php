<?php

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Contracts\Data\OnboardingChecklistData;
use App\Models\Organization;
use App\Models\User;

class BuildOnboardingChecklistAction
{
    public function handle(Organization $organization, User $user): OnboardingChecklistData
    {
        return new OnboardingChecklistData(
            hasRepository: $organization->repositories()->exists(),
            hasPersonalToken: $user->accessTokens()->whereNull('organization_uuid')->exists(),
            hasOrgToken: $organization->accessTokens()->exists(),
            isDismissed: $user->hasOnboardingDismissed($organization->uuid),
        );
    }
}
