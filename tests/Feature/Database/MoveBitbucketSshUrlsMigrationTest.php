<?php

use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;

beforeEach(function () {
    $this->package = Package::factory()->create(['repository_uuid' => null]);

    $this->runMigration = function () {
        $migration = require database_path('migrations/2026_10_28_000001_move_bitbucket_ssh_urls_to_ssh_host.php');

        $migration->up();
    };
});

it('moves stored Bitbucket SSH source urls to the SSH host', function () {
    $scp = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.0.0',
        'source_url' => 'git@bitbucket.org:acme/app.git',
    ]);
    $ssh = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.1.0',
        'source_url' => 'ssh://git@bitbucket.org/acme/app.git',
    ]);
    $sshWithPort = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.2.0',
        'source_url' => 'ssh://git@bitbucket.org:22/acme/app.git',
    ]);
    $https = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.3.0',
        'source_url' => 'https://bitbucket.org/acme/app.git',
    ]);
    $github = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.4.0',
        'source_url' => 'git@github.com:acme/app.git',
    ]);

    ($this->runMigration)();

    expect($scp->fresh()->source_url)->toBe('git@ssh.bitbucket.org:acme/app.git')
        ->and($ssh->fresh()->source_url)->toBe('ssh://git@ssh.bitbucket.org/acme/app.git')
        ->and($sshWithPort->fresh()->source_url)->toBe('ssh://git@ssh.bitbucket.org:22/acme/app.git')
        ->and($https->fresh()->source_url)->toBe('https://bitbucket.org/acme/app.git')
        ->and($github->fresh()->source_url)->toBe('git@github.com:acme/app.git');
});

it('serves moved source urls to clients revalidating with If-Modified-Since', function () {
    baseUrlServedSinceLongAgo();

    $organization = Organization::factory()->create(['slug' => 'acme']);
    $plainToken = 'test-token-'.uniqid();
    AccessToken::factory()
        ->forOrganization($organization)
        ->withPlainToken($plainToken)
        ->neverExpires()
        ->create();

    $package = Package::factory()->for($organization, 'organization')->create([
        'name' => 'acme/app',
        'repository_uuid' => null,
        'updated_at' => now()->subDays(30),
    ]);
    PackageVersion::factory()->for($package)->create([
        'version' => '1.0.0',
        'normalized_version' => '1.0.0.0',
        'source_url' => 'git@bitbucket.org:acme/app.git',
        'source_reference' => 'abc123',
        'updated_at' => now()->subDay(),
    ]);

    $lastModified = $this->withToken($plainToken)
        ->getJson('/acme/p2/acme/app.json')
        ->assertOk()
        ->headers->get('Last-Modified');

    ($this->runMigration)();

    $this->withToken($plainToken)
        ->withHeaders(['If-Modified-Since' => $lastModified])
        ->getJson('/acme/p2/acme/app.json')
        ->assertOk()
        ->assertJsonPath('packages.acme/app.0.source.url', 'git@ssh.bitbucket.org:acme/app.git');
});

it('moves generic Git repositories to the SSH host', function () {
    $repository = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'git@bitbucket.org:acme/app.git',
    ]);
    $repositoryWithPort = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'ssh://git@bitbucket.org:22/acme/lib.git',
    ]);
    $other = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'git@gitlab.com:acme/app.git',
    ]);

    ($this->runMigration)();

    expect($repository->fresh()->repo_identifier)->toBe('git@ssh.bitbucket.org:acme/app.git')
        ->and($repositoryWithPort->fresh()->repo_identifier)->toBe('ssh://git@ssh.bitbucket.org:22/acme/lib.git')
        ->and($other->fresh()->repo_identifier)->toBe('git@gitlab.com:acme/app.git');
});
