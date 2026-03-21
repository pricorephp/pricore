<?php

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function createBitbucketRepositoryWithWebhook(): Repository
{
    $organization = Organization::factory()->create();
    $secret = 'test-webhook-secret';

    return Repository::factory()
        ->forOrganization($organization)
        ->bitbucket()
        ->create([
            'webhook_id' => '{some-uuid-here}',
            'webhook_secret' => $secret,
        ]);
}

function signBitbucketPayload(string $payload, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $payload, $secret);
}

it('accepts a valid push event and dispatches sync', function () {
    Queue::fake();

    $repository = createBitbucketRepositoryWithWebhook();
    $payload = json_encode(['push' => ['changes' => []]]);
    $signature = signBitbucketPayload($payload, 'test-webhook-secret');

    $this->postJson(
        route('webhooks.bitbucket', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature' => $signature,
            'X-Event-Key' => 'repo:push',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Sync dispatched.']);

    Queue::assertPushed(SyncRepositoryJob::class, function ($job) use ($repository) {
        return $job->repository->uuid === $repository->uuid;
    });
});

it('rejects request with invalid signature', function () {
    $repository = createBitbucketRepositoryWithWebhook();
    $payload = json_encode(['push' => ['changes' => []]]);

    $this->postJson(
        route('webhooks.bitbucket', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature' => 'sha256=invalid',
            'X-Event-Key' => 'repo:push',
        ]
    )->assertForbidden();
});

it('rejects request with missing signature', function () {
    $repository = createBitbucketRepositoryWithWebhook();

    $this->postJson(
        route('webhooks.bitbucket', $repository->uuid),
        ['push' => ['changes' => []]],
        [
            'X-Event-Key' => 'repo:push',
        ]
    )->assertForbidden();
});

it('returns 404 for non-existent repository', function () {
    $this->postJson(
        route('webhooks.bitbucket', 'non-existent-uuid'),
        ['push' => ['changes' => []]],
        [
            'X-Hub-Signature' => 'sha256=anything',
            'X-Event-Key' => 'repo:push',
        ]
    )->assertNotFound();
});

it('rejects request when repository has no webhook secret', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->forOrganization($organization)
        ->bitbucket()
        ->create([
            'webhook_id' => null,
            'webhook_secret' => null,
        ]);

    $this->postJson(
        route('webhooks.bitbucket', $repository->uuid),
        ['push' => ['changes' => []]],
        [
            'X-Hub-Signature' => 'sha256=anything',
            'X-Event-Key' => 'repo:push',
        ]
    )->assertForbidden();
});

it('gracefully handles unknown event types', function () {
    $repository = createBitbucketRepositoryWithWebhook();
    $payload = json_encode(['issue' => ['id' => 1]]);
    $signature = signBitbucketPayload($payload, 'test-webhook-secret');

    $this->postJson(
        route('webhooks.bitbucket', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature' => $signature,
            'X-Event-Key' => 'issue:created',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Event ignored.']);
});
