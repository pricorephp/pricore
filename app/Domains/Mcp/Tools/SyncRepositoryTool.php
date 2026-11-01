<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Domains\Repository\Actions\RequestRepositorySyncAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('sync-repository')]
#[Description('Queue a sync of a repository so new tags and branches show up as package versions. Requires the admin or owner role.')]
#[IsIdempotent]
class SyncRepositoryTool extends Tool
{
    use InteractsWithOrganization;

    public function __construct(
        protected RequestRepositorySyncAction $requestRepositorySyncAction,
    ) {}

    public function handle(Request $request): Response
    {
        $organization = $this->organization($request, 'deleteRepository');

        $repositoryReference = $request->validate([
            'repository' => ['required', 'string', 'max:500'],
        ])['repository'];

        $repository = $organization->repositories()
            ->where(fn (Builder $query) => $query
                ->where('uuid', $repositoryReference)
                ->orWhere('repo_identifier', $repositoryReference)
                ->orWhere('name', $repositoryReference))
            ->first();

        if ($repository === null) {
            return Response::error("Repository [{$repositoryReference}] does not exist in this organization.");
        }

        $this->requestRepositorySyncAction->handle($repository);

        return Response::text("A sync of [{$repository->name}] has been queued. Check list-repositories for its status.");
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationSchema($schema),
            'repository' => $schema->string()->description('The repository uuid, name or identifier (e.g. acme/billing), as returned by list-repositories.')->required(),
        ];
    }
}
