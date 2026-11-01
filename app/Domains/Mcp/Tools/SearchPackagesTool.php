<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Models\Package;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('search-packages')]
#[Description('Search the Composer packages of an organization by name or description. Leave the query empty to list all packages.')]
#[IsReadOnly]
class SearchPackagesTool extends Tool
{
    use InteractsWithOrganization;

    public function handle(Request $request): Response|ResponseFactory
    {
        $organization = $this->organization($request);

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $validated['query'] ?? null;

        $packages = $organization->packages()
            ->with('repository')
            ->withCount('versions')
            ->when($query, fn (Builder $builder) => $builder->where(function (Builder $builder) use ($query) {
                $builder->whereLike('name', "%{$query}%")
                    ->orWhereLike('description', "%{$query}%");
            }))
            ->orderBy('name')
            ->limit($validated['limit'] ?? 25)
            ->get();

        if ($packages->isEmpty()) {
            return Response::text($query ? "No packages match [{$query}]." : 'This organization has no packages yet.');
        }

        return Response::structured([
            'packages' => $packages->map(fn (Package $package): array => [
                'name' => $package->name,
                'description' => $package->description,
                'type' => $package->type,
                'visibility' => $package->visibility,
                'latest_version' => $package->versions()->stable()->orderBySemanticVersion('desc')->value('version'),
                'versions_count' => $package->versions_count,
                'source' => match (true) {
                    $package->is_artifact => 'upload',
                    $package->mirror_uuid !== null => 'mirror',
                    default => $package->repository?->name,
                },
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
            'query' => $schema->string()->description('Text to match against the package name (vendor/name) or description.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Maximum number of packages to return. Defaults to 25.'),
        ];
    }
}
