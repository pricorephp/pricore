<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Organization\Actions\CreateOrganizationAction;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Contracts\Data\OrganizationStatsData;
use App\Domains\Organization\Requests\StoreOrganizationRequest;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(
        protected CreateOrganizationAction $createOrganization
    ) {}

    public function index(): Response
    {
        $organizations = auth()->user()
            ->organizations()
            ->withCount(['packages', 'repositories', 'accessTokens'])
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $org) => OrganizationData::fromModel($org));

        return Inertia::render('organizations/index', [
            'organizations' => $organizations,
        ]);
    }

    public function show(Organization $organization): Response
    {
        return Inertia::render('organizations/show', [
            'organization' => OrganizationData::fromModel($organization),
            'stats' => OrganizationStatsData::fromModel($organization),
        ]);
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $organization = $this->createOrganization->handle(
            name: $request->validated('name'),
            ownerUuid: auth()->user()->uuid
        );

        return redirect()->route('organizations.show', $organization->slug)
            ->with('success', 'Organization created successfully.');
    }
}
