<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Organization\Contracts\Data\GitCredentialData;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Http\Requests\StoreGitCredentialRequest;
use App\Domains\Organization\Http\Requests\UpdateGitCredentialRequest;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\Organization;
use App\Models\OrganizationGitCredential;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class GitCredentialController
{
    use AuthorizesRequests;

    public function index(Organization $organization): Response
    {
        $this->authorize('viewSettings', $organization);

        $credentials = $organization->gitCredentials()
            ->get()
            ->map(fn ($credential) => GitCredentialData::fromModel($credential));

        return Inertia::render('organizations/settings/git-credentials', [
            'organization' => OrganizationData::fromModel($organization),
            'credentials' => $credentials,
            'providers' => GitProvider::options(),
            'hasGitHubConnected' => auth()->user()?->hasGitHubConnected(),
        ]);
    }

    public function store(StoreGitCredentialRequest $request, Organization $organization): RedirectResponse
    {
        $provider = GitProvider::from($request->validated('provider'));

        if ($organization->gitCredentials()->where('provider', $provider)->exists()) {
            return redirect()
                ->route('organizations.settings.git-credentials.index', $organization)
                ->with('error', 'Credentials for this provider already exist. Please update the existing credentials instead.');
        }

        $user = $request->user();
        $isOAuth = $request->validated('source') === 'oauth';

        if ($isOAuth && $provider === GitProvider::GitHub) {
            if (! $user?->hasGitHubConnected()) {
                return redirect()
                    ->route('organizations.settings.git-credentials.index', $organization)
                    ->with('error', 'You must connect your GitHub account first.');
            }

            OrganizationGitCredential::create([
                'organization_uuid' => $organization->uuid,
                'provider' => $provider,
                'credentials' => ['token' => $user->github_token],
                'source_user_uuid' => $user->uuid,
            ]);
        } else {
            OrganizationGitCredential::create([
                'organization_uuid' => $organization->uuid,
                'provider' => $provider,
                'credentials' => $request->validated('credentials'),
            ]);
        }

        return redirect()
            ->route('organizations.settings.git-credentials.index', $organization)
            ->with('status', 'Git credentials added successfully.');
    }

    public function update(UpdateGitCredentialRequest $request, Organization $organization, OrganizationGitCredential $credential): RedirectResponse
    {
        if ($credential->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $credential->update([
            'credentials' => $request->validated('credentials'),
        ]);

        return redirect()
            ->route('organizations.settings.git-credentials.index', $organization)
            ->with('status', 'Git credentials updated successfully.');
    }

    public function destroy(Organization $organization, OrganizationGitCredential $credential): RedirectResponse
    {
        if ($credential->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $credential->delete();

        return redirect()
            ->route('organizations.settings.git-credentials.index', $organization)
            ->with('status', 'Git credentials removed successfully.');
    }
}
