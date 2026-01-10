<?php

namespace App\Domains\Organization\Http\Controllers;

use App\Domains\Organization\Actions\BuildOrganizationStatsAction;
use App\Domains\Organization\Actions\CreateOrganizationAction;
use App\Domains\Organization\Contracts\Data\OrganizationData;
use App\Domains\Organization\Requests\StoreOrganizationRequest;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function __construct(
        protected CreateOrganizationAction $createOrganization,
        protected BuildOrganizationStatsAction $buildStats,
    ) {}

    public function index(): Response
    {
        $user = auth()->user();

        if ($user === null) {
            abort(401);
        }

        $organizations = $user
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
            ->with('success', 'Organization created successfully.');
    }
}
