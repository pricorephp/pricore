<?php

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Paddle\Customer;
use Laravel\Paddle\Subscription;

uses()->group('billing');

beforeEach(function () {
    if (! class_exists(\PricoreCloud\PricoreCloudServiceProvider::class)) {
        $this->markTestSkipped('pricore-cloud is not installed.');
    }

    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Owner->value,
    ]);
});

it('owner can access billing page', function () {
    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/billing");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('organizations/settings/billing')
        ->has('organization')
        ->has('plan')
        ->has('plans')
    );
});

it('member cannot access billing page', function () {
    $member = User::factory()->create();
    $this->organization->members()->attach($member->uuid, [
        'uuid' => Str::uuid()->toString(),
        'role' => OrganizationRole::Member->value,
    ]);

    $response = $this->actingAs($member)->get("/organizations/{$this->organization->slug}/settings/billing");

    $response->assertForbidden();
});

it('shows free plan for unsubscribed organization', function () {
    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/billing");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('subscribed', false)
        ->where('onGracePeriod', false)
        ->where('endsAt', null)
        ->where('plan.plan', 'free')
    );
});

it('shows business plan for subscribed organization', function () {
    Customer::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'paddle_id' => 'ctm_test_'.Str::random(10),
        'name' => $this->organization->name,
        'email' => $this->user->email,
    ]);

    Subscription::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'type' => 'default',
        'paddle_id' => 'sub_test_'.Str::random(10),
        'status' => 'active',
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/billing");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('subscribed', true)
        ->where('onGracePeriod', false)
        ->where('plan.plan', 'business')
    );
});

it('shows grace period info for cancelled subscription', function () {
    Customer::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'paddle_id' => 'ctm_test_'.Str::random(10),
        'name' => $this->organization->name,
        'email' => $this->user->email,
    ]);

    Subscription::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'type' => 'default',
        'paddle_id' => 'sub_test_'.Str::random(10),
        'status' => 'canceled',
        'ends_at' => now()->addDays(15),
    ]);

    $response = $this->actingAs($this->user)->get("/organizations/{$this->organization->slug}/settings/billing");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('subscribed', true)
        ->where('onGracePeriod', true)
        ->whereType('endsAt', 'string')
    );
});

it('checkout endpoint validates price_id is required', function () {
    $response = $this->actingAs($this->user)->postJson("/organizations/{$this->organization->slug}/settings/billing/checkout", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['price_id']);
});

it('cancel endpoint cancels subscription', function () {
    config(['cashier.api_key' => 'test_api_key', 'cashier.sandbox' => true]);

    Customer::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'paddle_id' => 'ctm_test_'.Str::random(10),
        'name' => $this->organization->name,
        'email' => $this->user->email,
    ]);

    $paddleId = 'sub_test_'.Str::random(10);

    Subscription::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'type' => 'default',
        'paddle_id' => $paddleId,
        'status' => 'active',
    ]);

    Http::fake([
        'https://sandbox-api.paddle.com/*' => Http::response(['data' => [
            'id' => $paddleId,
            'status' => 'canceled',
            'scheduled_change' => [
                'action' => 'cancel',
                'effective_at' => now()->addDays(30)->toIso8601String(),
            ],
        ]]),
    ]);

    $response = $this->actingAs($this->user)->post("/organizations/{$this->organization->slug}/settings/billing/cancel");

    $response->assertRedirect();
});

it('resume endpoint stops cancellation', function () {
    config(['cashier.api_key' => 'test_api_key', 'cashier.sandbox' => true]);

    Customer::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'paddle_id' => 'ctm_test_'.Str::random(10),
        'name' => $this->organization->name,
        'email' => $this->user->email,
    ]);

    $paddleId = 'sub_test_'.Str::random(10);

    Subscription::create([
        'billable_id' => $this->organization->uuid,
        'billable_type' => 'organization',
        'type' => 'default',
        'paddle_id' => $paddleId,
        'status' => 'canceled',
        'ends_at' => now()->addDays(15),
    ]);

    Http::fake([
        'https://sandbox-api.paddle.com/*' => Http::response(['data' => [
            'id' => $paddleId,
            'status' => 'active',
            'scheduled_change' => null,
        ]]),
    ]);

    $response = $this->actingAs($this->user)->post("/organizations/{$this->organization->slug}/settings/billing/resume");

    $response->assertRedirect();
});
