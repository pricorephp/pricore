<?php

use App\Domains\Repository\Actions\RegisterWebhookAction;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can re-register a webhook via the sync webhook endpoint', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()
        ->forOrganization($organization)
        ->github()
        ->create();

    $mock = $this->mock(RegisterWebhookAction::class);
    $mock->shouldReceive('handle')
        ->once()
        ->with(\Mockery::on(fn ($repo) => $repo->uuid === $repository->uuid))
        ->andReturn(true);

    actingAs($user)
        ->post(route('organizations.repositories.webhook.sync', [$organization->slug, $repository->uuid]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Webhook registered successfully.');
});

it('shows error flash when webhook registration fails', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);
    $organization->members()->attach($user->uuid, ['role' => 'owner', 'uuid' => (string) Str::uuid()]);

    $repository = Repository::factory()
        ->forOrganization($organization)
        ->github()
        ->create();

    $mock = $this->mock(RegisterWebhookAction::class);
    $mock->shouldReceive('handle')
        ->once()
        ->andReturn(false);

    actingAs($user)
        ->post(route('organizations.repositories.webhook.sync', [$organization->slug, $repository->uuid]))
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('requires authentication to access webhook sync endpoint', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->forOrganization($organization)
        ->github()
        ->create();

    $this->post(route('organizations.repositories.webhook.sync', [$organization->slug, $repository->uuid]))
        ->assertRedirect(route('login'));
});
