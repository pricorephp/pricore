<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DismissOnboardingController extends Controller
{
    public function __invoke(Organization $organization): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $user->dismissOnboarding($organization->uuid);

        return back();
    }
}
