<?php

use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function githubProvider(): GitHubProvider
{
    return new GitHubProvider('', ['token' => 'github_pat_test']);
}

/**
 * @return array<string, mixed>
 */
function githubRepo(string $owner, string $ownerType, string $name): array
{
    return [
        'name' => $name,
        'full_name' => "{$owner}/{$name}",
        'private' => true,
        'description' => null,
        'owner' => ['login' => $owner, 'type' => $ownerType],
    ];
}

it('derives organization owners from reachable repositories when the token cannot list memberships', function () {
    Http::fake([
        'api.github.com/user/orgs*' => Http::response([], 200),
        'api.github.com/user/repos*' => Http::response([
            githubRepo('acme', 'Organization', 'billing'),
            githubRepo('acme', 'Organization', 'invoices'),
            githubRepo('someone-else', 'User', 'shared'),
        ], 200),
        'api.github.com/user' => Http::response(['login' => 'jane'], 200),
    ]);

    expect(githubProvider()->getOwners())->toBe(['jane', 'acme']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/user/repos')
        && $request['affiliation'] === 'collaborator,organization_member');
});

it('lists organization memberships without scanning repositories', function () {
    Http::fake([
        'api.github.com/user/orgs*' => Http::response([['login' => 'acme'], ['login' => 'globex']], 200),
        'api.github.com/user/repos*' => Http::response([githubRepo('initech', 'Organization', 'api')], 200),
        'api.github.com/user' => Http::response(['login' => 'jane'], 200),
    ]);

    expect(githubProvider()->getOwners())->toBe(['jane', 'acme', 'globex']);

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/user/repos'));
});

it('paginates through reachable repositories when deriving owners', function () {
    $page1 = array_map(fn (int $i) => githubRepo('acme', 'Organization', "repo-{$i}"), range(1, 100));

    Http::fake([
        'api.github.com/user/orgs*' => Http::response([], 200),
        'api.github.com/user/repos*page=2*' => Http::response([githubRepo('globex', 'Organization', 'api')], 200),
        'api.github.com/user/repos*' => Http::response($page1, 200),
        'api.github.com/user' => Http::response(['login' => 'jane'], 200),
    ]);

    expect(githubProvider()->getOwners())->toBe(['jane', 'acme', 'globex']);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/user/repos')
        && (int) $request['page'] === 2);
});
