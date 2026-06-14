<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Domains\Token\Contracts\Enums\TokenScope;
use App\Models\AccessToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Attach a user to an organization with the given role.
 */
function joinOrganization(Organization $organization, User $user, OrganizationRole $role = OrganizationRole::Member): void
{
    $organization->members()->attach($user->uuid, [
        'uuid' => (string) Str::uuid(),
        'role' => $role->value,
    ]);
}

/**
 * Create a personal access token for a user and return its plaintext value.
 *
 * @param  array<int, TokenScope>|null  $scopes  Null grants full (legacy) access.
 */
function personalAccessToken(User $user, ?array $scopes = null): string
{
    $plain = 'pat-'.Str::random(48);

    AccessToken::factory()
        ->forUser($user)
        ->withPlainToken($plain)
        ->neverExpires()
        ->state(['scopes' => $scopes === null ? null : array_map(fn (TokenScope $s) => $s->value, $scopes)])
        ->create();

    return $plain;
}

/**
 * Create an organization-scoped access token and return its plaintext value.
 *
 * @param  array<int, TokenScope>|null  $scopes  Null grants full (legacy) access.
 */
function organizationAccessToken(Organization $organization, ?array $scopes = null): string
{
    $plain = 'org-'.Str::random(48);

    AccessToken::factory()
        ->forOrganization($organization)
        ->withPlainToken($plain)
        ->neverExpires()
        ->state(['scopes' => $scopes === null ? null : array_map(fn (TokenScope $s) => $s->value, $scopes)])
        ->create();

    return $plain;
}
