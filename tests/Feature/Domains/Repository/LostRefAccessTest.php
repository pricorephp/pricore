<?php

use App\Domains\Repository\Actions\SyncRefAction;
use App\Domains\Repository\Contracts\Data\RefData;
use App\Domains\Repository\Contracts\Data\SyncRefResultData;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Exceptions\GitProviderRateLimitedException;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Support\Facades\Http;

const LOST_ACCESS_COMMIT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

function lostAccessRepository(string $provider): Repository
{
    $user = User::factory()->create();

    UserGitCredential::factory()->for($user, 'user')->create([
        'provider' => $provider,
        'credentials' => ['token' => 'secret-token'],
    ]);

    $repository = Repository::factory()
        ->forOrganization(Organization::factory()->create())
        ->create([
            'provider' => $provider,
            'repo_identifier' => 'acme/app',
            'credential_user_uuid' => $user->uuid,
        ]);

    $package = Package::factory()->forRepository($repository)->create(['name' => 'acme/app']);

    PackageVersion::factory()->forPackage($package)->create([
        'version' => 'v1.0.0',
        'source_reference' => LOST_ACCESS_COMMIT,
        'source_tag' => 'v1.0.0',
    ]);

    return $repository;
}

function syncLostAccessRef(Repository $repository): SyncRefResultData
{
    return app(SyncRefAction::class)->handle(
        GitProviderFactory::make($repository),
        $repository,
        new RefData(name: 'v1.0.0', commit: LOST_ACCESS_COMMIT, isTag: true),
    );
}

beforeEach(function () {
    config(['pricore.dist.enabled' => false]);
});

it('keeps the versions of a ref GitHub no longer lets it read', function () {
    $repository = lostAccessRepository('github');

    Http::fake([
        'api.github.com/repos/acme/app/contents/*' => Http::response(['message' => 'Not Found'], 404),
        'api.github.com/repos/acme/app/git/commits/'.LOST_ACCESS_COMMIT => Http::response(['message' => 'Not Found'], 404),
    ]);

    expect(fn () => syncLostAccessRef($repository))->toThrow(GitProviderException::class, 'HTTP 404');

    expect(PackageVersion::query()->where('version', 'v1.0.0')->exists())->toBeTrue()
        ->and($repository->emptyRefs()->count())->toBe(0);
});

it('removes the versions of a GitHub ref whose composer.json is gone', function () {
    $repository = lostAccessRepository('github');

    Http::fake([
        'api.github.com/repos/acme/app/contents/*' => Http::response(['message' => 'Not Found'], 404),
        'api.github.com/repos/acme/app/git/commits/'.LOST_ACCESS_COMMIT => Http::response(['sha' => LOST_ACCESS_COMMIT]),
    ]);

    $result = syncLostAccessRef($repository);

    expect($result->removed)->toBe(1)
        ->and($result->empty)->toBeTrue()
        ->and(PackageVersion::query()->count())->toBe(0)
        ->and($repository->emptyRefs()->pluck('commit', 'name')->all())->toBe(['v1.0.0' => LOST_ACCESS_COMMIT]);
});

it('waits out a GitHub rate limit while checking the ref', function () {
    $repository = lostAccessRepository('github');

    Http::fake([
        'api.github.com/repos/acme/app/contents/*' => Http::response(['message' => 'Not Found'], 404),
        'api.github.com/repos/acme/app/git/commits/*' => Http::response(['message' => 'API rate limit exceeded'], 403, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset' => (string) (time() + 120),
        ]),
    ]);

    expect(fn () => syncLostAccessRef($repository))->toThrow(GitProviderRateLimitedException::class);

    expect(PackageVersion::query()->count())->toBe(1)
        ->and($repository->emptyRefs()->count())->toBe(0);
});

it('keeps the versions of a ref GitLab no longer lets it read', function () {
    $repository = lostAccessRepository('gitlab');

    Http::fake([
        'gitlab.com/api/v4/projects/acme%2Fapp/repository/files/*' => Http::response(['message' => '404 Project Not Found'], 404),
        'gitlab.com/api/v4/projects/acme%2Fapp/repository/commits/'.LOST_ACCESS_COMMIT => Http::response(['message' => '404 Project Not Found'], 404),
    ]);

    expect(fn () => syncLostAccessRef($repository))->toThrow(GitProviderException::class, 'HTTP 404');

    expect(PackageVersion::query()->where('version', 'v1.0.0')->exists())->toBeTrue()
        ->and($repository->emptyRefs()->count())->toBe(0);
});

it('removes the versions of a GitLab ref whose composer.json is gone', function () {
    $repository = lostAccessRepository('gitlab');

    Http::fake([
        'gitlab.com/api/v4/projects/acme%2Fapp/repository/files/*' => Http::response(['message' => '404 File Not Found'], 404),
        'gitlab.com/api/v4/projects/acme%2Fapp/repository/commits/'.LOST_ACCESS_COMMIT => Http::response(['id' => LOST_ACCESS_COMMIT]),
    ]);

    $result = syncLostAccessRef($repository);

    expect($result->removed)->toBe(1)
        ->and($result->empty)->toBeTrue()
        ->and(PackageVersion::query()->count())->toBe(0);
});
