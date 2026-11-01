<?php

namespace App\Domains\Mcp\Tools\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;

trait InteractsWithOrganization
{
    protected function organizationSchema(JsonSchema $schema): Type
    {
        return $schema->string()
            ->description('The organization slug, as returned by list-organizations.')
            ->required();
    }

    protected function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('You must be signed in to use this tool.');
        }

        return $user;
    }

    /**
     * Resolve the organization from the request, scoped to the ones the user belongs to.
     */
    protected function organization(Request $request, string $ability = 'view'): Organization
    {
        $slug = $request->validate(['organization' => ['required', 'string']])['organization'];
        $user = $this->user($request);

        $organization = $user->organizations()->where('slug', $slug)->first();

        if ($organization === null) {
            throw new AuthorizationException("Organization [{$slug}] does not exist or you are not a member of it.");
        }

        if (Gate::forUser($user)->denies($ability, $organization)) {
            throw new AuthorizationException('Your role in this organization does not allow this action.');
        }

        return $organization;
    }
}
