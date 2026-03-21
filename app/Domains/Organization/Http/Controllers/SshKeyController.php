<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Organization\Actions\GenerateOrganizationSshKeyAction;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Contracts\Data\OrganizationSshKeyData;
use App\Models\Organization;
use App\Models\OrganizationSshKey;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SshKeyController
{
    use AuthorizesRequests;

    public function __construct(
        protected GenerateOrganizationSshKeyAction $generateOrganizationSshKeyAction,
        protected RecordActivityTask $recordActivityTask,
    ) {}

    public function index(Organization $organization): Response
    {
        $this->authorize('viewSettings', $organization);

        $sshKeys = $organization->sshKeys()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (OrganizationSshKey $organizationSshKey) => OrganizationSshKeyData::fromModel($organizationSshKey));

        return Inertia::render('organizations/settings/ssh-keys', [
            'organization' => OrganizationData::fromModel($organization),
            'sshKeys' => $sshKeys,
        ]);
    }

    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $organizationSshKey = $this->generateOrganizationSshKeyAction->handle($organization, $validated['name']);

        $this->recordActivityTask->handle(
            organization: $organization,
            type: ActivityType::SshKeyGenerated,
            subject: $organizationSshKey,
            actor: $request->user(),
            properties: ['name' => $organizationSshKey->name],
        );

        return redirect()
            ->route('organizations.settings.ssh-keys', $organization)
            ->with('status', 'SSH key generated successfully.');
    }

    public function destroy(Request $request, Organization $organization, OrganizationSshKey $sshKey): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        $this->recordActivityTask->handle(
            organization: $organization,
            type: ActivityType::SshKeyDeleted,
            subject: $sshKey,
            actor: $request->user(),
            properties: ['name' => $sshKey->name],
        );

        $sshKey->delete();

        return redirect()
            ->route('organizations.settings.ssh-keys', $organization)
            ->with('status', 'SSH key deleted successfully.');
    }
}
