<?php

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createGitLabRepositoryWithWebhook(): Repository
{
    $organization = Organization::factory()->create();
    $secret = 'test-gitlab-webhook-secret';

    return Repository::factory()
        ->forOrganization($organization)
        ->gitlab()
        ->create([
            'webhook_id' => '654321',
            'webhook_secret' => $secret,
        ]);
}

it('accepts a valid push event and dispatches sync', function () {
    Queue::fake();

    $repository = createGitLabRepositoryWithWebhook();

    $this->postJson(
        route('webhooks.gitlab', $repository->uuid),
        ['ref' => 'refs/heads/main'],
        [
            'X-Gitlab-Token' => 'test-gitlab-webhook-secret',
            'X-Gitlab-Event' => 'Push Hook',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Sync dispatched.']);

    Queue::assertPushed(SyncRepositoryJob::class, function ($job) use ($repository) {
        return $job->repository->uuid === $repository->uuid;
    });
});

it('accepts a valid tag push event and dispatches sync', function () {
    Queue::fake();

    $repository = createGitLabRepositoryWithWebhook();

    $this->postJson(
        route('webhooks.gitlab', $repository->uuid),
        ['ref' => 'refs/tags/v1.0.0'],
        [
            'X-Gitlab-Token' => 'test-gitlab-webhook-secret',
            'X-Gitlab-Event' => 'Tag Push Hook',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Sync dispatched.']);

    Queue::assertPushed(SyncRepositoryJob::class);
});

it('rejects request with invalid token', function () {
    $repository = createGitLabRepositoryWithWebhook();

    $this->postJson(
        route('webhooks.gitlab', $repository->uuid),
        ['ref' => 'refs/heads/main'],
        [
            'X-Gitlab-Token' => 'wrong-secret',
            'X-Gitlab-Event' => 'Push Hook',
        ]
    )->assertForbidden();
});

it('rejects request with missing token', function () {
    $repository = createGitLabRepositoryWithWebhook();

    $this->postJson(
        route('webhooks.gitlab', $repository->uuid),
        ['ref' => 'refs/heads/main'],
        [
            'X-Gitlab-Event' => 'Push Hook',
        ]
    )->assertForbidden();
});

it('returns 404 for non-existent repository', function () {
    $this->postJson(
        route('webhooks.gitlab', 'non-existent-uuid'),
        ['ref' => 'refs/heads/main'],
        [
            'X-Gitlab-Token' => 'anything',
            'X-Gitlab-Event' => 'Push Hook',
        ]
    )->assertNotFound();
});

it('rejects request when repository has no webhook secret', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->forOrganization($organization)
        ->gitlab()
        ->create([
            'webhook_id' => null,
            'webhook_secret' => null,
        ]);

    $this->postJson(
        route('webhooks.gitlab', $repository->uuid),
        ['ref' => 'refs/heads/main'],
        [
            'X-Gitlab-Token' => 'anything',
            'X-Gitlab-Event' => 'Push Hook',
        ]
    )->assertForbidden();
});

it('gracefully handles unknown event types', function () {
    $repository = createGitLabRepositoryWithWebhook();

    $this->postJson(
        route('webhooks.gitlab', $repository->uuid),
        ['action' => 'created'],
        [
            'X-Gitlab-Token' => 'test-gitlab-webhook-secret',
            'X-Gitlab-Event' => 'Issue Hook',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Event ignored.']);
});
