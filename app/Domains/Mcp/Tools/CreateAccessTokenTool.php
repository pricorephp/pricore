<?php

namespace App\Domains\Mcp\Tools;

use App\Domains\Mcp\Tools\Concerns\InteractsWithOrganization;
use App\Domains\Token\Actions\CreateAccessTokenAction;
use App\Domains\Token\Contracts\Enums\TokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-access-token')]
#[Description('Create an organization Composer token, e.g. for CI. The plain token is returned once and cannot be retrieved again. Requires the admin or owner role.')]
#[IsDestructive(false)]
class CreateAccessTokenTool extends Tool
{
    use InteractsWithOrganization;

    public function __construct(
        protected CreateAccessTokenAction $createAccessTokenAction,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $organization = $this->organization($request, 'viewSettings');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => [Rule::enum(TokenScope::class)],
        ]);

        $tokenCreatedData = $this->createAccessTokenAction->handle(
            organization: $organization,
            user: null,
            name: $validated['name'],
            expiresAt: isset($validated['expires_in_days']) ? now()->addDays((int) $validated['expires_in_days']) : null,
            scopes: $validated['scopes'] ?? [TokenScope::Composer],
            actor: $this->user($request),
        );

        $domain = parse_url(url('/'), PHP_URL_HOST);

        return Response::structured([
            'name' => $tokenCreatedData->name,
            'token' => $tokenCreatedData->plainToken,
            'expires_at' => $tokenCreatedData->expiresAt?->toIso8601String(),
            'scopes' => $tokenCreatedData->scopes,
            'composer_auth' => "composer config --global --auth http-basic.{$domain} token {$tokenCreatedData->plainToken}",
            'note' => 'Store this token now; Pricore only keeps a hash of it.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'organization' => $this->organizationSchema($schema),
            'name' => $schema->string()->description('A name that says where the token is used, e.g. "GitHub Actions".')->required(),
            'expires_in_days' => $schema->integer()->min(1)->max(3650)->description('Days until the token expires. Leave out for a token that never expires.'),
            'scopes' => $schema->array()
                ->items($schema->string()->enum(array_column(TokenScope::cases(), 'value')))
                ->description('What the token may do. "composer" installs packages, "write:packages" publishes uploaded versions and the other scopes grant access to the REST API. Defaults to ["composer"].'),
        ];
    }
}
