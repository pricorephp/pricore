<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Models\Organization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-organizations')]
#[Description('List the Pricore organizations you are a member of, with your role and the Composer repository URL of each.')]
#[IsReadOnly]
class ListOrganizationsTool extends Tool
{
    use InteractsWithOrganization;

    public function handle(Request $request): ResponseFactory
    {
        $organizations = $this->user($request)->organizations()
            ->withCount('packages')
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization): array => [
                'slug' => $organization->slug,
                'name' => $organization->name,
                'role' => $organization->pivot?->role->value,
                'packages_count' => $organization->packages_count,
                'composer_repository_url' => url("/{$organization->slug}"),
            ]);

        return Response::structured(['organizations' => $organizations->all()]);
    }

    /**
     * @return array<string, never>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
