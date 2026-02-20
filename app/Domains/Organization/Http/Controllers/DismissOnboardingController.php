<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;

class DismissOnboardingController extends Controller
{
    public function __invoke(Organization $organization): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $user->dismissOnboarding($organization->uuid);

        return back();
    }
}
