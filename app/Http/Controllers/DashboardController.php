<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $organization = $user->lastAccessedOrganization() ?? $user->organizations()->first();

        if ($organization) {
            return redirect()->route('organizations.show', $organization);
        }

        return Inertia::render('dashboard');
    }
}
