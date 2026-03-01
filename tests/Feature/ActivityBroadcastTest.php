<?php

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Domains\Activity\Events\ActivityRecorded;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('dispatches ActivityRecorded event when activity is recorded', function () {
    Event::fake([ActivityRecorded::class]);

    $user = User::factory()->create();
    $organization = Organization::factory()->create(['owner_uuid' => $user->uuid]);

    $recordActivityTask = app(RecordActivityTask::class);
    $recordActivityTask->handle(
        organization: $organization,
        type: ActivityType::MemberAdded,
        actor: $user,
        properties: ['name' => $user->name],
    );

    Event::assertDispatched(ActivityRecorded::class, function (ActivityRecorded $event) use ($organization) {
        return $event->organizationUuid === $organization->uuid
            && $event->activityType === ActivityType::MemberAdded->value;
    });
});

it('broadcasts ActivityRecorded on the correct private channel', function () {
    $organization = Organization::factory()->create();

    $event = new ActivityRecorded(
        $organization->uuid,
        ActivityType::RepositorySynced->value,
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0]->name)->toBe("private-organization.{$organization->uuid}");
});

it('uses the correct broadcast event name for ActivityRecorded', function () {
    $event = new ActivityRecorded('org-uuid', 'member_joined');

    expect($event->broadcastAs())->toBe('activity.recorded');
});
