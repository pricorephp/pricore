<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class DismissOnboardingController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Organization $organization): RedirectResponse
    {
        $this->authorize('view', $organization);

        /** @var User $user */
        $user = auth()->user();

        $user->dismissOnboarding($organization->uuid);

        return back();
    }
}
