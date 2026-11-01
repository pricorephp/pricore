<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Actions\CreateGitCloneAction;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Organization;
use App\Models\OrganizationSshKey;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses()->group('security', 'repositories', 'ssh-keys');

beforeEach(function () {
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    Queue::fake();
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Admin->value,
    ]);
});

it('rejects another organization SSH key without creating or syncing a repository', function () {
    $foreignKey = OrganizationSshKey::factory()->create();

    $this->actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization), [
            'provider' => 'git',
            'repo_identifier' => 'git@git.example.com:private/package.git',
            'ssh_key_uuid' => $foreignKey->uuid,
        ])
        ->assertSessionHasErrors('ssh_key_uuid');

    $this->assertDatabaseCount('repositories', 0);
    Queue::assertNothingPushed();
    Process::assertNothingRan();
    Http::assertNothingSent();
});

it('allows a repository with its own organization key or no key', function (bool $withKey) {
    $key = $withKey ? OrganizationSshKey::factory()->create([
        'organization_uuid' => $this->organization->uuid,
    ]) : null;

    $this->actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization), [
            'provider' => 'git',
            'repo_identifier' => 'git@git.example.com:private/package.git',
            'ssh_key_uuid' => $key?->uuid,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->organization->repositories()->sole()->ssh_key_uuid)->toBe($key?->uuid);
    Queue::assertPushed(SyncRepositoryJob::class);
})->with([true, false]);

it('rejects a stored foreign key before a provider can use it', function () {
    $key = OrganizationSshKey::factory()->create();
    $repository = Repository::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'provider' => 'git',
        'repo_identifier' => 'git@git.example.com:private/package.git',
        'ssh_key_uuid' => $key->uuid,
    ]);

    expect(fn () => GitProviderFactory::make($repository))
        ->toThrow(GitProviderException::class, 'The SSH key does not belong to the repository organization.');
    Process::assertNothingRan();
});

it('rejects a stored foreign key before cloning or fetching', function (bool $existingClone) {
    $key = OrganizationSshKey::factory()->create();
    $repository = Repository::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'provider' => 'git',
        'repo_identifier' => 'git@git.example.com:private/package.git',
        'ssh_key_uuid' => $key->uuid,
    ]);
    $clonePath = storage_path("app/git-clones/{$repository->uuid}");

    try {
        if ($existingClone) {
            File::ensureDirectoryExists($clonePath);
        }

        expect(fn () => app(CreateGitCloneAction::class)->handle($repository))
            ->toThrow(GitProviderException::class, 'The SSH key does not belong to the repository organization.');
        Process::assertNothingRan();
    } finally {
        if ($existingClone) {
            File::deleteDirectory($clonePath);
        }
    }
})->with([true, false]);

it('uses an authorized key for both provider requests and cloning then removes the temporary key', function () {
    $key = OrganizationSshKey::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'private_key' => 'test-authorized-private-key',
    ]);
    $repository = Repository::factory()->create([
        'organization_uuid' => $this->organization->uuid,
        'provider' => 'git',
        'repo_identifier' => 'git@git.example.com:private/package.git',
        'ssh_key_uuid' => $key->uuid,
    ]);
    $keyPaths = [];
    Process::fake(function ($process) use (&$keyPaths) {
        preg_match('/ssh -i (\S+)/', $process->environment['GIT_SSH_COMMAND'], $matches);
        $keyPaths[] = $matches[1];
        expect(trim(file_get_contents($matches[1])))->toBe('test-authorized-private-key');

        return Process::result();
    });

    expect(GitProviderFactory::make($repository)->validateCredentials())->toBeTrue();
    app(CreateGitCloneAction::class)->handle($repository);

    expect($keyPaths)->toHaveCount(2);
    foreach ($keyPaths as $keyPath) {
        expect(file_exists($keyPath))->toBeFalse();
    }
});
