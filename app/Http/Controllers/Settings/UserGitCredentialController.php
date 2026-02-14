<?php

namespace App\Http\Controllers\Settings;

use App\Domains\Organization\Contracts\Data\GitCredentialData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserGitCredentialRequest;
use App\Http\Requests\Settings\UpdateUserGitCredentialRequest;
use App\Models\UserGitCredential;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserGitCredentialController extends Controller
{
    public function index(): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $credentials = $user->gitCredentials()
            ->get()
            ->map(fn ($credential) => GitCredentialData::fromModel($credential));

        return Inertia::render('settings/git-credentials', [
            'credentials' => $credentials,
            'providers' => GitProvider::options(),
            'githubConnectUrl' => route('settings.github.connect'),
        ]);
    }

    public function store(StoreUserGitCredentialRequest $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $provider = GitProvider::from($request->validated('provider'));

        if ($user->gitCredentials()->where('provider', $provider)->exists()) {
            return redirect()
                ->route('settings.git-credentials')
                ->with('error', 'Credentials for this provider already exist. Please update the existing credentials instead.');
        }

        UserGitCredential::create([
            'user_uuid' => $user->uuid,
            'provider' => $provider,
            'credentials' => $request->validated('credentials'),
        ]);

        return redirect()
            ->route('settings.git-credentials')
            ->with('status', 'Git credentials added successfully.');
    }

    public function update(UpdateUserGitCredentialRequest $request, UserGitCredential $credential): RedirectResponse
    {
        $credential->update([
            'credentials' => $request->validated('credentials'),
        ]);

        return redirect()
            ->route('settings.git-credentials')
            ->with('status', 'Git credentials updated successfully.');
    }

    public function destroy(UserGitCredential $credential): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($credential->user_uuid !== $user->uuid) {
            abort(403);
        }

        $credential->delete();

        return redirect()
            ->route('settings.git-credentials')
            ->with('status', 'Git credentials removed successfully.');
    }
}
