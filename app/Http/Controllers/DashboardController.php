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
            $redirect = redirect()->route('organizations.show', $organization);

            if ($request->session()->has('status')) {
                $redirect->with('status', $request->session()->get('status'));
            }

            if ($request->session()->has('error')) {
                $redirect->with('error', $request->session()->get('error'));
            }

            return $redirect;
        }

        return Inertia::render('dashboard');
    }
}
