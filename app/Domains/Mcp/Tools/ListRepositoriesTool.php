<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Models\Repository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-repositories')]
#[Description('List the Git repositories connected to an organization, with their sync status and the packages they provide.')]
#[IsReadOnly]
class ListRepositoriesTool extends Tool
{
    use InteractsWithOrganization;

    public function handle(Request $request): Response|ResponseFactory
    {
        $repositories = $this->organization($request)->repositories()
            ->with('packages:uuid,repository_uuid,name')
            ->orderBy('name')
            ->get();

        if ($repositories->isEmpty()) {
            return Response::text('This organization has no repositories yet.');
        }

        return Response::structured([
            'repositories' => $repositories->map(fn (Repository $repository): array => [
                'uuid' => $repository->uuid,
                'name' => $repository->name,
                'provider' => $repository->provider->value,
                'identifier' => $repository->repo_identifier,
                'default_branch' => $repository->default_branch,
                'sync_status' => $repository->sync_status?->value,
                'last_synced_at' => $repository->last_synced_at?->toIso8601String(),
                'packages' => $repository->packages->pluck('name')->all(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationSchema($schema),
        ];
    }
}
