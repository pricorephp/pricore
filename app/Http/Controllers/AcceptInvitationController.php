<?php

namespace App\Http\Controllers;

use App\Domains\Organization\Actions\AcceptOrganizationInvitationAction;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = OrganizationInvitation::with('organization', 'invitedBy')
            ->where('token', $token)
            ->first();

        if (! $invitation || $invitation->isAccepted()) {
            return Inertia::render('invitations/accept', [
                'error' => 'This invitation is invalid or has already been accepted.',
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('invitations/accept', [
                'error' => 'This invitation has expired.',
            ]);
        }

        session(['invitation_token' => $token]);

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'organizationName' => $invitation->organization->name,
                'role' => $invitation->role->label(),
                'invitedByName' => $invitation->invitedBy?->name,
                'expiresAt' => $invitation->expires_at->toISOString(),
            ],
            'token' => $token,
            'isAuthenticated' => auth()->check(),
        ]);
    }

    public function accept(Request $request, string $token, AcceptOrganizationInvitationAction $acceptAction): RedirectResponse
    {
        $invitation = OrganizationInvitation::with('organization')
            ->where('token', $token)
            ->first();

        if (! $invitation || ! $invitation->isPending()) {
            return redirect()->route('dashboard')
                ->with('error', 'This invitation is no longer valid.');
        }

        /** @var User $user */
        $user = $request->user();

        if ($invitation->organization->members()->where('user_uuid', $user->uuid)->exists()) {
            $invitation->update(['accepted_at' => now()]);

            return redirect()->route('organizations.show', $invitation->organization)
                ->with('status', 'You are already a member of this organization.');
        }

        $acceptAction->handle($invitation, $user);

        session()->forget('invitation_token');

        return redirect()->route('organizations.show', $invitation->organization)
            ->with('status', "You have joined {$invitation->organization->name}.");
    }
}
