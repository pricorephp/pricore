<?php

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses()->group('activity');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'role' => 'owner',
        'uuid' => (string) Str::uuid(),
    ]);
});

describe('RecordActivityTask', function () {
    it('records an activity log entry', function () {
        $action = app(RecordActivityTask::class);

        $log = $action->handle(
            organization: $this->organization,
            type: ActivityType::RepositoryAdded,
            actor: $this->user,
            properties: ['name' => 'test-repo'],
        );

        expect($log)->toBeInstanceOf(ActivityLog::class);
        assertDatabaseHas('activity_logs', [
            'uuid' => $log->uuid,
            'organization_uuid' => $this->organization->uuid,
            'actor_uuid' => $this->user->uuid,
            'type' => 'repository.added',
        ]);
    });

    it('records activity without an actor for system actions', function () {
        $action = app(RecordActivityTask::class);

        $log = $action->handle(
            organization: $this->organization,
            type: ActivityType::RepositorySynced,
            properties: ['name' => 'test-repo', 'versions_added' => 5],
        );

        expect($log->actor_uuid)->toBeNull();
        expect($log->properties)->toHaveKey('versions_added', 5);
    });

    it('records activity with a polymorphic subject', function () {
        $repository = Repository::factory()->create([
            'organization_uuid' => $this->organization->uuid,
        ]);

        $action = app(RecordActivityTask::class);

        $log = $action->handle(
            organization: $this->organization,
            type: ActivityType::RepositoryAdded,
            subject: $repository,
            actor: $this->user,
        );

        expect($log->subject_type)->toBe($repository->getMorphClass());
        expect($log->subject_uuid)->toBe($repository->uuid);
    });
});

describe('activity recording on repository actions', function () {
    it('records activity when a repository is deleted', function () {
        $repository = Repository::factory()->create([
            'organization_uuid' => $this->organization->uuid,
        ]);

        actingAs($this->user)
            ->delete(route('organizations.repositories.destroy', [
                $this->organization->slug,
                $repository->uuid,
            ]));

        assertDatabaseHas('activity_logs', [
            'organization_uuid' => $this->organization->uuid,
            'type' => 'repository.removed',
            'actor_uuid' => $this->user->uuid,
        ]);
    });
});

describe('activity recording on token actions', function () {
    it('records activity when a token is created', function () {
        actingAs($this->user)
            ->post(route('organizations.settings.tokens.store', $this->organization->slug), [
                'name' => 'Test Token',
            ]);

        assertDatabaseHas('activity_logs', [
            'organization_uuid' => $this->organization->uuid,
            'type' => 'token.created',
        ]);
    });

    it('records activity when a token is revoked', function () {
        $token = $this->organization->accessTokens()->create([
            'name' => 'Test Token',
            'token_hash' => hash('sha256', 'test-token'),
        ]);

        actingAs($this->user)
            ->delete(route('organizations.settings.tokens.destroy', [
                $this->organization->slug,
                $token->uuid,
            ]));

        assertDatabaseHas('activity_logs', [
            'organization_uuid' => $this->organization->uuid,
            'type' => 'token.revoked',
        ]);
    });
});

describe('activity recording on member actions', function () {
    it('records activity when a member role is changed', function () {
        $member = User::factory()->create();
        $this->organization->members()->attach($member->uuid, [
            'role' => 'member',
            'uuid' => (string) Str::uuid(),
        ]);

        $organizationUser = OrganizationUser::where('user_uuid', $member->uuid)
            ->where('organization_uuid', $this->organization->uuid)
            ->first();

        actingAs($this->user)
            ->patch(route('organizations.settings.members.update', [
                $this->organization->slug,
                $organizationUser->uuid,
            ]), [
                'role' => 'admin',
            ]);

        assertDatabaseHas('activity_logs', [
            'organization_uuid' => $this->organization->uuid,
            'type' => 'member.role_changed',
            'actor_uuid' => $this->user->uuid,
        ]);
    });

    it('records activity when a member is removed', function () {
        $member = User::factory()->create();
        $this->organization->members()->attach($member->uuid, [
            'role' => 'member',
            'uuid' => (string) Str::uuid(),
        ]);

        $organizationUser = OrganizationUser::where('user_uuid', $member->uuid)
            ->where('organization_uuid', $this->organization->uuid)
            ->first();

        actingAs($this->user)
            ->delete(route('organizations.settings.members.destroy', [
                $this->organization->slug,
                $organizationUser->uuid,
            ]));

        assertDatabaseHas('activity_logs', [
            'organization_uuid' => $this->organization->uuid,
            'type' => 'member.removed',
            'actor_uuid' => $this->user->uuid,
        ]);
    });
});

describe('activity feed on dashboard', function () {
    it('includes activity logs as a deferred prop', function () {
        $action = app(RecordActivityTask::class);

        $action->handle(
            organization: $this->organization,
            type: ActivityType::RepositoryAdded,
            actor: $this->user,
            properties: ['name' => 'test-repo'],
        );

        $response = actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('organizations/show')
        );
    });
});
