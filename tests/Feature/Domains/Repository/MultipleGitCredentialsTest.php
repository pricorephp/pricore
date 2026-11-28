<?php

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake([SyncRepositoryJob::class]);
    Http::fake(['*' => Http::response([])]);

    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $this->gitlabCom = UserGitCredential::factory()->for($this->user, 'user')->gitlab()->create([
        'credentials' => ['token' => 'glpat-public', 'url' => null],
    ]);
    $this->selfHosted = UserGitCredential::factory()->for($this->user, 'user')->gitlab()->create([
        'name' => 'Acme',
        'credentials' => ['token' => 'glpat-acme', 'url' => 'https://gitlab.acme.test'],
    ]);
});

it('links a repository to the chosen credential and its instance', function () {
    $this->actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization), [
            'provider' => 'gitlab',
            'repo_identifier' => 'acme/billing',
            'git_credential_uuid' => $this->selfHosted->uuid,
        ])
        ->assertSessionHasNoErrors();

    $repository = Repository::where('repo_identifier', 'acme/billing')->sole();

    expect($repository->git_credential_uuid)->toBe($this->selfHosted->uuid)
        ->and($repository->custom_base_url)->toBe('https://gitlab.acme.test')
        ->and(GitProviderFactory::resolveCredential($repository)?->is($this->selfHosted))->toBeTrue();
});

it('uses the oldest credential for the provider when none is chosen', function () {
    $this->actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization), [
            'provider' => 'gitlab',
            'repo_identifier' => 'acme/billing',
        ])
        ->assertSessionHasNoErrors();

    expect(Repository::where('repo_identifier', 'acme/billing')->sole()->git_credential_uuid)
        ->toBe($this->gitlabCom->uuid);
});

it('rejects a credential of another user or provider', function (UserGitCredential $credential) {
    $this->actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization), [
            'provider' => 'gitlab',
            'repo_identifier' => 'acme/billing',
            'git_credential_uuid' => $credential->uuid,
        ])
        ->assertSessionHasErrors('git_credential_uuid');

    expect(Repository::count())->toBe(0);
})->with([
    'another user' => fn () => UserGitCredential::factory()->gitlab()->create(),
    'another provider' => fn () => UserGitCredential::factory()->for($this->user, 'user')->github()->create(),
]);

it('only skips repositories already imported from the same instance', function () {
    Repository::factory()->for($this->organization, 'organization')->gitlab()->create([
        'repo_identifier' => 'acme/billing',
        'custom_base_url' => null,
    ]);

    $this->actingAs($this->user)
        ->post(route('organizations.repositories.bulk-store', $this->organization), [
            'provider' => 'gitlab',
            'git_credential_uuid' => $this->selfHosted->uuid,
            'repositories' => [['repo_identifier' => 'acme/billing']],
        ])
        ->assertSessionHasNoErrors();

    $imported = Repository::where('git_credential_uuid', $this->selfHosted->uuid)->sole();

    expect(Repository::where('repo_identifier', 'acme/billing')->count())->toBe(2)
        ->and($imported->custom_base_url)->toBe('https://gitlab.acme.test')
        ->and($imported->credential_user_uuid)->toBe($this->user->uuid);
});

it('lists suggestions from the chosen credential', function () {
    $this->actingAs($this->user)
        ->getJson(route('organizations.repositories.suggest', [
            'organization' => $this->organization,
            'provider' => 'gitlab',
            'owner' => 'acme',
            'credential' => $this->selfHosted->uuid,
        ]))
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://gitlab.acme.test/api/v4')
        && $request->hasHeader('Authorization', 'Bearer glpat-acme'));
});

it('falls back to the only credential serving the host of an unlinked repository', function () {
    $repository = Repository::factory()->for($this->organization, 'organization')->gitlab()->create([
        'credential_user_uuid' => $this->user->uuid,
        'custom_base_url' => 'https://gitlab.acme.test',
    ]);

    expect(GitProviderFactory::resolveCredential($repository)?->is($this->selfHosted))->toBeTrue();
});

it('does not guess between several credentials for an unlinked repository', function () {
    UserGitCredential::factory()->for($this->user, 'user')->gitlab()->create([
        'credentials' => ['token' => 'glpat-second', 'url' => 'https://gitlab.acme.test/'],
    ]);

    $repository = Repository::factory()->for($this->organization, 'organization')->gitlab()->create([
        'credential_user_uuid' => $this->user->uuid,
        'custom_base_url' => 'https://gitlab.acme.test',
    ]);

    expect(GitProviderFactory::resolveCredential($repository))->toBeNull();
});

it('unlinks repositories when their credential is removed', function () {
    $repository = Repository::factory()->for($this->organization, 'organization')->gitlab()->create([
        'credential_user_uuid' => $this->user->uuid,
        'git_credential_uuid' => $this->selfHosted->uuid,
    ]);

    $this->actingAs($this->user)
        ->delete(route('settings.git-credentials.destroy', $this->selfHosted))
        ->assertRedirect(route('settings.git-credentials'));

    expect($repository->fresh()->git_credential_uuid)->toBeNull()
        ->and(GitProviderFactory::resolveCredential($repository->fresh()))->toBeNull();
});

it('does not import a repository twice when the instance URL is written differently', function () {
    $this->gitlabCom->update(['credentials' => ['token' => 'glpat-public', 'url' => 'https://GitLab.com/']]);

    Repository::factory()->for($this->organization, 'organization')->gitlab()->create([
        'repo_identifier' => 'acme/billing',
        'custom_base_url' => null,
    ]);

    $this->actingAs($this->user)
        ->post(route('organizations.repositories.bulk-store', $this->organization), [
            'provider' => 'gitlab',
            'git_credential_uuid' => $this->gitlabCom->uuid,
            'repositories' => [['repo_identifier' => 'acme/billing']],
        ])
        ->assertSessionHasNoErrors();

    expect(Repository::where('repo_identifier', 'acme/billing')->count())->toBe(1);
});

it('only skips repositories on GitLab.com when importing without credentials', function () {
    $otherUser = User::factory()->create();
    $this->organization->members()->attach($otherUser->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    Repository::factory()->for($this->organization, 'organization')->gitlab()->create([
        'repo_identifier' => 'acme/billing',
        'custom_base_url' => 'https://gitlab.acme.test',
    ]);

    $this->actingAs($otherUser)
        ->post(route('organizations.repositories.bulk-store', $this->organization), [
            'provider' => 'gitlab',
            'repositories' => [['repo_identifier' => 'acme/billing']],
        ])
        ->assertSessionHasNoErrors();

    expect(Repository::where('repo_identifier', 'acme/billing')->whereNull('custom_base_url')->exists())->toBeTrue();
});

it('refuses to roll back while users have several credentials for a provider', function () {
    $migration = require database_path('migrations/2026_11_08_000001_allow_multiple_git_credentials_per_provider.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect(Schema::hasColumn('repositories', 'git_credential_uuid'))->toBeTrue();
});
