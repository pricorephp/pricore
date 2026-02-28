<?php

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function createRepositoryWithWebhook(): Repository
{
    $organization = Organization::factory()->create();
    $secret = 'test-webhook-secret';

    return Repository::factory()
        ->forOrganization($organization)
        ->github()
        ->create([
            'webhook_id' => '123456',
            'webhook_secret' => $secret,
        ]);
}

function signPayload(string $payload, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $payload, $secret);
}

it('accepts a valid push event and dispatches sync', function () {
    Queue::fake();

    $repository = createRepositoryWithWebhook();
    $payload = json_encode(['ref' => 'refs/heads/main']);
    $signature = signPayload($payload, 'test-webhook-secret');

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => $signature,
            'X-GitHub-Event' => 'push',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Sync dispatched.']);

    Queue::assertPushed(SyncRepositoryJob::class, function ($job) use ($repository) {
        return $job->repository->uuid === $repository->uuid;
    });
});

it('accepts a valid release event and dispatches sync', function () {
    Queue::fake();

    $repository = createRepositoryWithWebhook();
    $payload = json_encode(['action' => 'published']);
    $signature = signPayload($payload, 'test-webhook-secret');

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => $signature,
            'X-GitHub-Event' => 'release',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Sync dispatched.']);

    Queue::assertPushed(SyncRepositoryJob::class);
});

it('responds to ping event', function () {
    $repository = createRepositoryWithWebhook();
    $payload = json_encode(['zen' => 'Keep it logically awesome.']);
    $signature = signPayload($payload, 'test-webhook-secret');

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => $signature,
            'X-GitHub-Event' => 'ping',
        ]
    )->assertOk()
        ->assertJson(['message' => 'pong']);
});

it('rejects request with invalid signature', function () {
    $repository = createRepositoryWithWebhook();
    $payload = json_encode(['ref' => 'refs/heads/main']);

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => 'sha256=invalid',
            'X-GitHub-Event' => 'push',
        ]
    )->assertForbidden();
});

it('rejects request with missing signature', function () {
    $repository = createRepositoryWithWebhook();

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        ['ref' => 'refs/heads/main'],
        [
            'X-GitHub-Event' => 'push',
        ]
    )->assertForbidden();
});

it('returns 404 for non-existent repository', function () {
    $this->postJson(
        route('webhooks.github', 'non-existent-uuid'),
        ['ref' => 'refs/heads/main'],
        [
            'X-Hub-Signature-256' => 'sha256=anything',
            'X-GitHub-Event' => 'push',
        ]
    )->assertNotFound();
});

it('rejects request when repository has no webhook secret', function () {
    $organization = Organization::factory()->create();
    $repository = Repository::factory()
        ->forOrganization($organization)
        ->github()
        ->create([
            'webhook_id' => null,
            'webhook_secret' => null,
        ]);

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        ['ref' => 'refs/heads/main'],
        [
            'X-Hub-Signature-256' => 'sha256=anything',
            'X-GitHub-Event' => 'push',
        ]
    )->assertForbidden();
});

it('accepts a delete event and dispatches sync', function () {
    Queue::fake();

    $repository = createRepositoryWithWebhook();
    $payload = json_encode(['ref' => 'feature-branch', 'ref_type' => 'branch']);
    $signature = signPayload($payload, 'test-webhook-secret');

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => $signature,
            'X-GitHub-Event' => 'delete',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Sync dispatched.']);

    Queue::assertPushed(SyncRepositoryJob::class, function ($job) use ($repository) {
        return $job->repository->uuid === $repository->uuid;
    });
});

it('gracefully handles unknown event types', function () {
    $repository = createRepositoryWithWebhook();
    $payload = json_encode(['action' => 'created']);
    $signature = signPayload($payload, 'test-webhook-secret');

    $this->postJson(
        route('webhooks.github', $repository->uuid),
        json_decode($payload, true),
        [
            'X-Hub-Signature-256' => $signature,
            'X-GitHub-Event' => 'issues',
        ]
    )->assertOk()
        ->assertJson(['message' => 'Event ignored.']);
});
