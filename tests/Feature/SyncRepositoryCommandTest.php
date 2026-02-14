<?php

use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake all HTTP requests to GitHub API
    Http::fake([
        'api.github.com/repos/*/tags*' => Http::response([
            [
                'name' => 'v1.0.0',
                'commit' => ['sha' => 'abc123'],
            ],
        ]),
        'api.github.com/repos/*/branches*' => Http::response([
            [
                'name' => 'main',
                'commit' => ['sha' => 'def456'],
            ],
        ]),
        'api.github.com/repos/*/contents/composer.json*' => Http::response([
            'type' => 'file',
            'content' => base64_encode(json_encode([
                'name' => 'vendor/package',
                'description' => 'Test package',
                'type' => 'library',
            ])),
        ]),
        'api.github.com/repos/*' => Http::response([
            'name' => 'test-repo',
            'full_name' => 'owner/test-repo',
        ]),
    ]);
});

it('displays error when no repository is specified', function () {
    $this->artisan('sync:repository')
        ->expectsOutput('Please specify a repository, organization, or use --all flag.')
        ->assertFailed();
});

it('displays error when repository is not found', function () {
    $this->artisan('sync:repository', ['repository' => 'non-existent-uuid'])
        ->expectsOutput("Repository 'non-existent-uuid' not found.")
        ->assertFailed();
});

it('displays error when organization is not found', function () {
    $this->artisan('sync:repository', ['--organization' => 'non-existent'])
        ->expectsOutput("Organization 'non-existent' not found.")
        ->assertFailed();
});

it('can sync a specific repository', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->create(['credential_user_uuid' => $user->uuid]);

    UserGitCredential::factory()
        ->for($user, 'user')
        ->github()
        ->create();

    $this->artisan('sync:repository', ['repository' => $repository->uuid])
        ->expectsOutput('Found 1 repository/repositories to sync.')
        ->expectsOutput('All sync jobs have been dispatched successfully.')
        ->assertSuccessful();
});

it('can sync all repositories for an organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['slug' => 'test-org']);

    Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->count(3)
        ->create(['credential_user_uuid' => $user->uuid]);

    UserGitCredential::factory()
        ->for($user, 'user')
        ->github()
        ->create();

    $this->artisan('sync:repository', ['--organization' => 'test-org'])
        ->expectsOutput('Found 3 repository/repositories to sync.')
        ->assertSuccessful();
});

it('can sync all repositories with --all flag', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    Repository::factory()
        ->for($organization, 'organization')
        ->github()
        ->count(2)
        ->create(['credential_user_uuid' => $user->uuid]);

    UserGitCredential::factory()
        ->for($user, 'user')
        ->github()
        ->create();

    $this->artisan('sync:repository --all')
        ->expectsOutput('Found 2 repository/repositories to sync.')
        ->assertSuccessful();
});
