<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Organization\Actions\BuildOrganizationStatsAction;
use App\Domains\Organization\Actions\CreateOrganizationAction;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Requests\StoreOrganizationRequest;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected CreateOrganizationAction $createOrganization,
        protected BuildOrganizationStatsAction $buildStats,
    ) {}

    public function index(): RedirectResponse
    {
        return redirect('/');
    }

    public function show(Organization $organization): Response
    {
        return Inertia::render('organizations/show', [
            'organization' => OrganizationData::fromModel($organization),
            'stats' => $this->buildStats->handle($organization),
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            abort(401);
        }

        $organization = $this->createOrganization->handle(
            name: $request->validated('name'),
            ownerUuid: $user->uuid
        );

        return redirect()->route('organizations.show', $organization->slug)
            ->with('status', 'Organization created successfully.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('dashboard')
            ->with('status', 'Organization deleted successfully.');
    }
}
