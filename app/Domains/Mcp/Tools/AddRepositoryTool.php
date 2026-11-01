<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Domains\Repository\Actions\CreateRepositoryAction;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Rules\ValidPackagePath;
use App\Domains\Repository\Rules\ValidRepositoryIdentifier;
use App\Domains\Repository\Services\PackagePaths\PackagePathPattern;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('add-repository')]
#[Description('Connect a Git repository to an organization and start its first sync. GitHub, GitLab and Bitbucket repositories use the Git credentials you connected in Pricore. Requires the admin or owner role.')]
#[IsDestructive(false)]
class AddRepositoryTool extends Tool
{
    use InteractsWithOrganization;

    public function __construct(
        protected CreateRepositoryAction $createRepositoryAction,
    ) {}

    public function handle(Request $request): Response
    {
        $organization = $this->organization($request, 'deleteRepository');

        $input = $request->all();

        if (isset($input['package_paths'])) {
            $input['package_paths'] = PackagePathPattern::parseInput($input['package_paths']);
        }

        $validated = Validator::validate($input, [
            'provider' => ['required', 'string', Rule::enum(GitProvider::class)],
            'repo_identifier' => [
                'required',
                'string',
                'max:500',
                new ValidRepositoryIdentifier(GitProvider::tryFrom((string) ($input['provider'] ?? ''))),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'default_branch' => ['nullable', 'string', 'max:255'],
            'ssh_key_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('organization_ssh_keys', 'uuid')->where('organization_uuid', $organization->uuid),
            ],
            'package_paths' => ['nullable', 'array', 'max:50'],
            'package_paths.*' => ['string', 'max:255', new ValidPackagePath],
        ]);

        $repository = $this->createRepositoryAction->handle(
            organization: $organization,
            provider: GitProvider::from($validated['provider']),
            repoIdentifier: $validated['repo_identifier'],
            user: $this->user($request),
            name: $validated['name'] ?? null,
            defaultBranch: $validated['default_branch'] ?? null,
            sshKeyUuid: $validated['ssh_key_uuid'] ?? null,
            packagePaths: ($validated['package_paths'] ?? []) !== [] ? array_values($validated['package_paths']) : null,
        );

        $message = "Repository [{$repository->name}] was added and its first sync has been queued.";

        if ($repository->webhook_id === null && $repository->provider->supportsAutomaticWebhooks()) {
            $message .= ' Registering the webhook failed, so new tags will not sync automatically until it is retried from the repository page.';
        }

        return Response::text($message);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationSchema($schema),
            'provider' => $schema->string()->enum(array_column(GitProvider::cases(), 'value'))->description('The Git provider. Use "git" for any other Git server reachable over SSH or HTTPS.')->required(),
            'repo_identifier' => $schema->string()->description('The repository, e.g. acme/billing for GitHub, GitLab and Bitbucket, or a clone URL for "git".')->required(),
            'name' => $schema->string()->description('A display name. Defaults to the repository name.'),
            'default_branch' => $schema->string()->description('The branch to treat as default. Defaults to the provider\'s default branch.'),
            'ssh_key_uuid' => $schema->string()->description('For "git" repositories over SSH: the uuid of one of the organization\'s SSH keys.'),
            'package_paths' => $schema->array()->items($schema->string())->description('For monorepos: directories or glob patterns that contain a composer.json, e.g. packages/*.'),
        ];
    }
}
