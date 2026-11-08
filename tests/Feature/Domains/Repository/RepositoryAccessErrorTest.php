<?php

use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Exceptions\GitProviderRateLimitedException;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Domains\Repository\Services\GitProviders\BitbucketProvider;
use App\Domains\Repository\Services\GitProviders\GenericGitProvider;
use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use App\Domains\Repository\Services\GitProviders\GitLabProvider;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function selfHostedGitLabProvider(): GitLabProvider
{
    return new GitLabProvider('acme/group/package', ['token' => 'glpat-secret', 'url' => 'https://gitlab.acme.test/']);
}

it('explains why a self-hosted GitLab refused access', function (int $status, string $message) {
    Http::fake(['gitlab.acme.test/api/v4/projects/*' => Http::response(['message' => 'refused'], $status)]);

    expect(fn () => selfHostedGitLabProvider()->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, $message);
})->with([
    'rejected token' => [401, 'GitLab at https://gitlab.acme.test rejected the token (HTTP 401)'],
    'missing scope' => [403, 'GitLab at https://gitlab.acme.test denied access to project acme/group/package (HTTP 403). The token needs the api scope'],
    'hidden project' => [404, 'GitLab at https://gitlab.acme.test could not find project acme/group/package (HTTP 404)'],
    'unexpected status' => [418, 'GitLab at https://gitlab.acme.test responded with HTTP 418'],
]);

it('points at the credentials to change', function (int $status) {
    Http::fake(['gitlab.acme.test/*' => Http::response([], $status)]);

    expect(fn () => selfHostedGitLabProvider()->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, 'the person who added this repository; they can update them under Settings > Git Providers');
})->with([401, 403, 404]);

it('suggests setting the GitLab URL when the request went to GitLab.com', function (int $status) {
    Http::fake(['gitlab.com/*' => Http::response([], $status)]);

    expect(fn () => (new GitLabProvider('acme/package', ['token' => 'glpat-secret']))->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, 'For a self-hosted instance, also set the GitLab URL in the credentials.');
})->with([401, 404]);

it('leaves out the GitLab URL suggestion when one is set', function () {
    Http::fake(['gitlab.acme.test/*' => Http::response([], 404)]);

    try {
        selfHostedGitLabProvider()->ensureRepositoryAccess();
        $this->fail('Expected a GitProviderException');
    } catch (GitProviderException $e) {
        expect($e->getMessage())->not->toContain('self-hosted');
    }
});

it('says when a GitLab instance cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host'));

    expect(fn () => selfHostedGitLabProvider()->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, 'Could not connect to GitLab at https://gitlab.acme.test. Check the GitLab URL');
});

it('says when the credentials are missing', function (GitLabProvider|GitHubProvider|BitbucketProvider $provider, string $url, string $message) {
    Http::fake([$url => Http::response([], 401)]);

    expect(fn () => $provider->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, $message);
})->with([
    'gitlab' => fn () => [new GitLabProvider('acme/package', []), 'gitlab.com/*', 'No GitLab token is available'],
    'github' => fn () => [new GitHubProvider('acme/package', []), 'api.github.com/*', 'No GitHub token is available'],
    'bitbucket' => fn () => [new BitbucketProvider('acme/package', []), 'api.bitbucket.org/*', 'No Bitbucket API token is available'],
]);

it('explains why GitHub refused access', function (int $status, string $message) {
    Http::fake(['api.github.com/*' => Http::response([], $status)]);

    expect(fn () => (new GitHubProvider('acme/package', ['token' => 'secret']))->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, $message);
})->with([
    [401, 'GitHub rejected the token (HTTP 401)'],
    [403, 'a fine-grained token needs access to this repository with read permission on Contents'],
    [404, 'a fine-grained token must include this repository'],
]);

it('explains why Bitbucket refused access', function (int $status, string $message) {
    Http::fake(['api.bitbucket.org/*' => Http::response([], $status)]);

    $bitbucketProvider = new BitbucketProvider('acme/package', ['email' => 'dev@acme.test', 'api_token' => 'secret']);

    expect(fn () => $bitbucketProvider->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, $message);
})->with([
    [401, 'Bitbucket rejected the email and API token (HTTP 401)'],
    [403, 'The API token needs the read:repository:bitbucket scope'],
    [404, 'Bitbucket could not find acme/package (HTTP 404)'],
]);

it('passes on what git reported for a generic repository', function () {
    Process::fake(['*' => Process::result(errorOutput: "git@git.acme.test: Permission denied (publickey).\n", exitCode: 128)]);

    expect(fn () => (new GenericGitProvider('git@git.acme.test:acme/package.git', []))->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, 'Permission denied (publickey)');
});

it('keeps credentials in a generic repository URL out of the message', function (string $url, string $expected, string $secret) {
    Process::fake(['*' => Process::result(errorOutput: "fatal: unable to access '{$url}/': 403\n", exitCode: 128)]);

    try {
        (new GenericGitProvider($url, []))->ensureRepositoryAccess();
        $this->fail('Expected a GitProviderException');
    } catch (GitProviderException $e) {
        expect($e->getMessage())
            ->toContain("Could not read {$expected}.")
            ->toContain("unable to access '{$expected}/'")
            ->not->toContain($secret);
    }
})->with([
    'password' => ['https://dev:hunter2@git.acme.test/package.git', 'https://***@git.acme.test/package.git', 'hunter2'],
    'token as username' => ['https://glpat-secret@git.acme.test/package.git', 'https://***@git.acme.test/package.git', 'glpat-secret'],
    'password with an at sign' => ['https://dev:hun@ter2@git.acme.test/package.git', 'https://***@git.acme.test/package.git', 'ter2'],
    'ssh password' => ['ssh://git:hunter2@git.acme.test/package.git', 'ssh://git:***@git.acme.test/package.git', 'hunter2'],
]);

it('keeps credentials out of the message when git does not finish', function () {
    Process::fake(fn () => throw new RuntimeException('git timed out'));

    $genericGitProvider = new GenericGitProvider('https://dev:hunter2@git.acme.test/package.git', []);

    expect(fn () => $genericGitProvider->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, 'Could not read https://***@git.acme.test/package.git: git did not finish.');
});

it('leaves a generic repository URL without credentials as it is', function (string $url) {
    Process::fake(['*' => Process::result(errorOutput: "fatal: repository not found\n", exitCode: 128)]);

    expect(fn () => (new GenericGitProvider($url, []))->ensureRepositoryAccess())
        ->toThrow(GitProviderException::class, "Could not read {$url}.");
})->with([
    'ssh user' => 'ssh://git@git.acme.test:22/package.git',
    'scp style' => 'git@git.acme.test:acme/package.git',
    'port and at sign in the path' => 'https://git.acme.test:8443/acme/package@v1.git',
]);

it('does not reword a rate limit', function () {
    Http::fake(['gitlab.acme.test/*' => Http::response([], 429, ['Retry-After' => '30'])]);

    expect(fn () => selfHostedGitLabProvider()->ensureRepositoryAccess())
        ->toThrow(fn (GitProviderRateLimitedException $e) => expect($e->retryAfter)->toBe(30));
});

it('passes when the repository can be read', function () {
    Http::fake(['gitlab.acme.test/*' => Http::response(['id' => 1])]);

    selfHostedGitLabProvider()->ensureRepositoryAccess();

    Http::assertSentCount(1);
});

it('shows the explanation in the sync log without exposing the token', function () {
    Http::fake(['gitlab.acme.test/*' => Http::response(['message' => '401 Unauthorized'], 401)]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $credential = UserGitCredential::factory()->for($user, 'user')->gitlab()->create([
        'credentials' => ['token' => 'glpat-secret', 'url' => 'https://gitlab.acme.test'],
    ]);

    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->gitlab()
        ->create([
            'credential_user_uuid' => $user->uuid,
            'git_credential_uuid' => $credential->uuid,
            'custom_base_url' => 'https://gitlab.acme.test',
            'repo_identifier' => 'acme/package',
        ]);

    expect(fn () => SyncRepositoryJob::dispatchSync($repository))->toThrow(GitProviderException::class);

    $errorMessage = $repository->syncLogs()->latest('started_at')->value('error_message');

    expect($errorMessage)
        ->toContain('GitLab at https://gitlab.acme.test rejected the token (HTTP 401)')
        ->not->toContain('glpat-secret');
});
