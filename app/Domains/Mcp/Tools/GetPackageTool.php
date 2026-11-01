<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Models\PackageVersion;
use App\Models\SecurityAdvisoryMatch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-package')]
#[Description('Get the details of a package: its versions, the requirements of one version (the latest stable by default) and the security advisories affecting that version.')]
#[IsReadOnly]
class GetPackageTool extends Tool
{
    use InteractsWithOrganization;

    public function handle(Request $request): Response|ResponseFactory
    {
        $organization = $this->organization($request);

        $validated = $request->validate([
            'package' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:255'],
            'versions' => ['nullable', 'string', 'in:all,stable,dev'],
        ]);

        $package = $organization->packages()
            ->with('repository')
            ->where('name', $validated['package'])
            ->first();

        if ($package === null) {
            return Response::error("Package [{$validated['package']}] does not exist in this organization.");
        }

        $filter = $validated['versions'] ?? 'all';

        $versions = $package->versions()
            ->when($filter === 'stable', fn ($query) => $query->stable())
            ->when($filter === 'dev', fn ($query) => $query->dev())
            ->orderBySemanticVersion('desc')
            ->limit(30)
            ->get(['uuid', 'version', 'source_reference', 'released_at']);

        $selected = isset($validated['version'])
            ? $package->versions()->matchingVersion($validated['version'])->first()
            : ($package->versions()->stable()->orderBySemanticVersion('desc')->first()
                ?? $package->versions()->orderBySemanticVersion('desc')->first());

        if (isset($validated['version']) && $selected === null) {
            return Response::error("Version [{$validated['version']}] of [{$package->name}] does not exist.");
        }

        return Response::structured([
            'name' => $package->name,
            'description' => $package->description,
            'type' => $package->type,
            'visibility' => $package->visibility,
            'repository' => $package->repository ? [
                'name' => $package->repository->name,
                'provider' => $package->repository->provider->value,
                'identifier' => $package->repository->repo_identifier,
                'sync_status' => $package->repository->sync_status?->value,
                'last_synced_at' => $package->repository->last_synced_at?->toIso8601String(),
            ] : null,
            'install' => "composer require {$package->name}",
            'composer_repository_url' => url("/{$organization->slug}"),
            'versions' => $versions->map(fn (PackageVersion $version): array => [
                'version' => $version->version,
                'reference' => $version->source_reference,
                'released_at' => $version->released_at?->toIso8601String(),
            ])->all(),
            'selected_version' => $selected ? $this->versionDetails($selected) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function versionDetails(PackageVersion $version): array
    {
        $composerJson = $version->composer_json;

        return [
            'version' => $version->version,
            'reference' => $version->source_reference,
            'released_at' => $version->released_at?->toIso8601String(),
            'require' => $composerJson['require'] ?? [],
            'require_dev' => $composerJson['require-dev'] ?? [],
            'autoload' => $composerJson['autoload'] ?? null,
            'advisories' => $version->advisoryMatches()
                ->with('advisory')
                ->get()
                ->map(fn (SecurityAdvisoryMatch $match): array => [
                    'advisory_id' => $match->advisory->advisory_id,
                    'title' => $match->advisory->title,
                    'severity' => $match->advisory->severity->value,
                    'cve' => $match->advisory->cve,
                    'affected_package' => $match->dependency_name ?? $match->advisory->package_name,
                    'affected_versions' => $match->advisory->affected_versions,
                    'link' => $match->advisory->link,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationSchema($schema),
            'package' => $schema->string()->description('The full package name, e.g. acme/billing.')->required(),
            'version' => $schema->string()->description('A specific version to inspect, e.g. 2.1.0 or dev-main. Defaults to the latest stable version.'),
            'versions' => $schema->string()->enum(['all', 'stable', 'dev'])->description('Which versions to list (at most 30, newest first). Defaults to all.'),
        ];
    }
}
