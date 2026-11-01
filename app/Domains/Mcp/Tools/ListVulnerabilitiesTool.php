<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Domains\Security\Actions\CollectRelevantVersionUuidsAction;
use App\Domains\Security\Contracts\Enums\AdvisorySeverity;
use App\Models\SecurityAdvisoryMatch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-vulnerabilities')]
#[Description('List the security advisories affecting an organization\'s packages, either directly or through their dependencies. Only the latest stable version and the dev branches of each package are checked, like the Security page.')]
#[IsReadOnly]
class ListVulnerabilitiesTool extends Tool
{
    use InteractsWithOrganization;

    protected const LIMIT = 200;

    public function __construct(
        protected CollectRelevantVersionUuidsAction $collectRelevantVersionUuidsAction,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $organization = $this->organization($request);

        $validated = $request->validate([
            'package' => ['nullable', 'string', 'max:255'],
            'severity' => ['nullable', Rule::enum(AdvisorySeverity::class)],
        ]);

        // Sort before limiting so the most severe advisories are never the ones cut off
        $matches = SecurityAdvisoryMatch::query()
            ->select('security_advisory_matches.*')
            ->join('security_advisories', 'security_advisories.uuid', '=', 'security_advisory_matches.security_advisory_uuid')
            ->with(['advisory', 'packageVersion.package'])
            ->whereIn('security_advisory_matches.package_version_uuid', $this->collectRelevantVersionUuidsAction->handle($organization))
            ->when($validated['package'] ?? null, fn (Builder $query, string $package) => $query
                ->whereHas('packageVersion.package', fn (Builder $query) => $query->where('name', $package)))
            ->when($validated['severity'] ?? null, fn (Builder $query, string $severity) => $query
                ->where('security_advisories.severity', $severity))
            ->tap(function (Builder $query) {
                $severities = collect(AdvisorySeverity::cases())->sortByDesc(fn (AdvisorySeverity $severity) => $severity->weight());

                foreach ($severities as $severity) {
                    $query->orderByRaw('security_advisories.severity = ? DESC', [$severity->value]);
                }
            })
            ->orderBy('security_advisory_matches.uuid')
            ->limit(self::LIMIT + 1)
            ->get();

        if ($matches->isEmpty()) {
            return Response::text('No known vulnerabilities affect these packages.');
        }

        $truncated = $matches->count() > self::LIMIT;

        return Response::structured(array_filter([
            'vulnerabilities' => $matches->take(self::LIMIT)->map(fn (SecurityAdvisoryMatch $match): array => [
                'package' => $match->packageVersion->package->name,
                'version' => $match->packageVersion->version,
                'match_type' => $match->match_type->value,
                'affected_package' => $match->dependency_name ?? $match->advisory->package_name,
                'affected_versions' => $match->advisory->affected_versions,
                'advisory_id' => $match->advisory->advisory_id,
                'title' => $match->advisory->title,
                'severity' => $match->advisory->severity->value,
                'cve' => $match->advisory->cve,
                'link' => $match->advisory->link,
            ])->values()->all(),
            'truncated' => $truncated,
            'note' => $truncated
                ? 'Only the '.self::LIMIT.' most severe matches are shown. Filter by package or severity to see the rest.'
                : null,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationSchema($schema),
            'package' => $schema->string()->description('Only show advisories for this package, e.g. acme/billing.'),
            'severity' => $schema->string()->enum(array_column(AdvisorySeverity::cases(), 'value'))->description('Only show advisories of this severity.'),
        ];
    }
}
