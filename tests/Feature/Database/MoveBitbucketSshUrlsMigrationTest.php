<?php

use App\Models\Package;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Support\Facades\File;

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
    $https = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.2.0',
        'source_url' => 'https://bitbucket.org/acme/app.git',
    ]);
    $github = PackageVersion::factory()->for($this->package)->create([
        'version' => '1.3.0',
        'source_url' => 'git@github.com:acme/app.git',
    ]);

    ($this->runMigration)();

    expect($scp->fresh()->source_url)->toBe('git@ssh.bitbucket.org:acme/app.git')
        ->and($ssh->fresh()->source_url)->toBe('ssh://git@ssh.bitbucket.org/acme/app.git')
        ->and($https->fresh()->source_url)->toBe('https://bitbucket.org/acme/app.git')
        ->and($github->fresh()->source_url)->toBe('git@github.com:acme/app.git');
});

it('moves generic Git repositories to the SSH host and drops their stale clone', function () {
    $repository = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'git@bitbucket.org:acme/app.git',
    ]);
    $other = Repository::factory()->create([
        'provider' => 'git',
        'repo_identifier' => 'git@gitlab.com:acme/app.git',
    ]);

    $clonePath = storage_path("app/git-clones/{$repository->uuid}");
    $otherClonePath = storage_path("app/git-clones/{$other->uuid}");
    File::ensureDirectoryExists($clonePath);
    File::ensureDirectoryExists($otherClonePath);

    try {
        ($this->runMigration)();

        expect($repository->fresh()->repo_identifier)->toBe('git@ssh.bitbucket.org:acme/app.git')
            ->and($other->fresh()->repo_identifier)->toBe('git@gitlab.com:acme/app.git')
            ->and(is_dir($clonePath))->toBeFalse()
            ->and(is_dir($otherClonePath))->toBeTrue();
    } finally {
        File::deleteDirectory($clonePath);
        File::deleteDirectory($otherClonePath);
    }
});
