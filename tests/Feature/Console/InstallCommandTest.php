<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a user and organization successfully', function () {
    $this->artisan('pricore:install')
        ->expectsQuestion('Your Name', 'John Doe')
        ->expectsQuestion('Email', 'john@example.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Confirm Password', 'password123')
        ->expectsQuestion('Organization name', 'My Organization')
        ->expectsOutputToContain('Pricore has been set up successfully!')
        ->assertSuccessful();

    $user = User::query()->where('email', 'john@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('John Doe')
        ->and($user->email_verified_at)->not->toBeNull();

    $organization = Organization::query()->where('name', 'My Organization')->first();

    expect($organization)->not->toBeNull()
        ->and($organization->slug)->toBe('my-organization')
        ->and($organization->owner_uuid)->toBe($user->uuid);

    expect($organization->members()->where('user_uuid', $user->uuid)->first())
        ->not->toBeNull()
        ->pivot->role->toBe(OrganizationRole::Owner);
});

it('warns when users already exist and allows aborting', function () {
    User::factory()->create();

    $this->artisan('pricore:install')
        ->expectsConfirmation('Users already exist. Do you want to continue?', 'no')
        ->expectsOutputToContain('Installation cancelled.')
        ->assertSuccessful();
});

it('warns when users already exist and allows continuing', function () {
    User::factory()->create();

    $this->artisan('pricore:install')
        ->expectsConfirmation('Users already exist. Do you want to continue?', 'yes')
        ->expectsQuestion('Your Name', 'Jane Doe')
        ->expectsQuestion('Email', 'jane@example.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Confirm Password', 'password123')
        ->expectsQuestion('Organization name', 'Another Org')
        ->assertSuccessful();

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('does not warn when no users exist', function () {
    expect(User::query()->count())->toBe(0);

    $this->artisan('pricore:install')
        ->expectsQuestion('Your Name', 'John Doe')
        ->expectsQuestion('Email', 'john@example.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Confirm Password', 'password123')
        ->expectsQuestion('Organization name', 'My Org')
        ->assertSuccessful();
});

it('creates the user with a verified email', function () {
    $this->artisan('pricore:install')
        ->expectsQuestion('Your Name', 'John Doe')
        ->expectsQuestion('Email', 'john@example.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Confirm Password', 'password123')
        ->expectsQuestion('Organization name', 'My Org')
        ->assertSuccessful();

    $user = User::query()->where('email', 'john@example.com')->first();

    expect($user->email_verified_at)->not->toBeNull();
});

it('creates the organization with the correct slug', function () {
    $this->artisan('pricore:install')
        ->expectsQuestion('Your Name', 'John Doe')
        ->expectsQuestion('Email', 'john@example.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Confirm Password', 'password123')
        ->expectsQuestion('Organization name', 'Acme Corp')
        ->assertSuccessful();

    $organization = Organization::query()->where('name', 'Acme Corp')->first();

    expect($organization)
        ->not->toBeNull()
        ->slug->toBe('acme-corp');
});

it('hashes the user password', function () {
    $this->artisan('pricore:install')
        ->expectsQuestion('Your Name', 'John Doe')
        ->expectsQuestion('Email', 'john@example.com')
        ->expectsQuestion('Password', 'password123')
        ->expectsQuestion('Confirm Password', 'password123')
        ->expectsQuestion('Organization name', 'My Org')
        ->assertSuccessful();

    $user = User::query()->where('email', 'john@example.com')->first();

    expect($user->password)->not->toBe('password123');
    expect(password_verify('password123', $user->password))->toBeTrue();
});
