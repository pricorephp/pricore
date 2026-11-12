<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Rules\ValidRepositoryIdentifier;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

uses()->group('security', 'repositories');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);
});

/**
 * git executes the command in an `ext::` address, reads the local filesystem for
 * `file://` and bare paths, and parses a leading dash as an option — so none of
 * these may ever reach the CLI.
 */
it('rejects git identifiers that would give git control of the host', function (string $identifier) {
    actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization->slug), [
            'provider' => GitProvider::Git->value,
            'repo_identifier' => $identifier,
        ])
        ->assertSessionHasErrors('repo_identifier');

    assertDatabaseCount('repositories', 0);
})->with([
    'ext transport' => 'ext::sh -c "id > /tmp/pwned"',
    'ext transport uppercase' => 'EXT::sh -c id',
    'other remote helper' => 'fd::7',
    'file scheme' => 'file:///etc/passwd',
    'absolute path' => '/etc/passwd',
    'relative path' => '../../etc/passwd',
    'option injection' => '--upload-pack=id',
    'short option' => '-u',
    'empty' => ' ',
]);

it('accepts ordinary git remote URLs', function (string $identifier) {
    expect(ValidRepositoryIdentifier::passes($identifier, GitProvider::Git))->toBeTrue();
})->with([
    'https' => 'https://git.example.com/acme/widgets.git',
    'http' => 'http://git.example.com/acme/widgets.git',
    'ssh scheme' => 'ssh://git@git.example.com:2222/acme/widgets.git',
    'git scheme' => 'git://git.example.com/acme/widgets.git',
    'scp shorthand' => 'git@git.example.com:acme/widgets.git',
]);

it('rejects git identifiers on a non-public address', function (string $identifier) {
    $this->hostResolver->set('git.internal', ['10.0.0.10']);

    actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization->slug), [
            'provider' => GitProvider::Git->value,
            'repo_identifier' => $identifier,
        ])
        ->assertSessionHasErrors('repo_identifier');

    assertDatabaseCount('repositories', 0);
})->with([
    'loopback' => 'http://127.0.0.1:8080/',
    'link local' => 'http://169.254.169.254/latest/meta-data/',
    'private over git' => 'git://10.0.0.5/acme/widgets.git',
    'private over ssh' => 'ssh://git@192.168.1.1:2222/acme/widgets.git',
    'private scp shorthand' => 'git@192.168.1.1:acme/widgets.git',
    'name resolving to a private address' => 'https://git.internal/acme/widgets.git',
]);

it('accepts a git identifier on a private host that is explicitly configured', function () {
    config()->set('pricore.git.allowed_private_hosts', ['git.internal']);
    $this->hostResolver->set('git.internal', ['10.0.0.10']);
    Queue::fake();

    actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization->slug), [
            'provider' => GitProvider::Git->value,
            'repo_identifier' => 'https://git.internal/acme/widgets.git',
        ])
        ->assertSessionHasNoErrors();

    assertDatabaseCount('repositories', 1);
});

it('rejects slugs that are not owner/repository', function (string $identifier) {
    actingAs($this->user)
        ->post(route('organizations.repositories.store', $this->organization->slug), [
            'provider' => GitProvider::GitHub->value,
            'repo_identifier' => $identifier,
        ])
        ->assertSessionHasErrors('repo_identifier');

    assertDatabaseCount('repositories', 0);
})->with([
    'url instead of slug' => 'https://github.com/acme/widgets',
    'traversal' => 'acme/../../../etc',
    'single segment' => 'acme',
    'trailing segment' => 'acme/widgets/extra',
]);

it('allows gitlab subgroup nesting but not github', function () {
    expect(ValidRepositoryIdentifier::passes('group/subgroup/widgets', GitProvider::GitLab))->toBeTrue()
        ->and(ValidRepositoryIdentifier::passes('group/subgroup/widgets', GitProvider::GitHub))->toBeFalse();
});

it('rejects unsafe identifiers on the bulk import endpoint', function () {
    actingAs($this->user)
        ->post(route('organizations.repositories.bulk-store', $this->organization->slug), [
            'provider' => GitProvider::Git->value,
            'repositories' => [
                ['repo_identifier' => 'https://git.example.com/acme/widgets.git'],
                ['repo_identifier' => 'ext::sh -c id'],
            ],
        ])
        ->assertSessionHasErrors('repositories.1.repo_identifier');

    assertDatabaseCount('repositories', 0);
});

it('rejects identifiers on a non-public address on the bulk import endpoint', function () {
    actingAs($this->user)
        ->post(route('organizations.repositories.bulk-store', $this->organization->slug), [
            'provider' => GitProvider::Git->value,
            'repositories' => [
                ['repo_identifier' => 'https://git.example.com/acme/widgets.git'],
                ['repo_identifier' => 'http://127.0.0.1:8080/'],
            ],
        ])
        ->assertSessionHasErrors('repositories.1.repo_identifier');

    assertDatabaseCount('repositories', 0);
});
