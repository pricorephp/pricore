<?php

use App\Domains\Package\Actions\RecordPackageViewTask;
use App\Models\Organization;
use App\Models\Package;
use App\Models\PackageView;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses()->group('package-views');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create(['owner_uuid' => $this->user->uuid]);
    $this->organization->members()->attach($this->user->uuid, [
        'role' => 'owner',
        'uuid' => (string) Str::uuid(),
    ]);
    $this->package = Package::factory()->create([
        'organization_uuid' => $this->organization->uuid,
    ]);
});

describe('RecordPackageViewTask', function () {
    it('creates a package view record on first view', function () {
        $task = app(RecordPackageViewTask::class);

        $task->handle($this->user, $this->package);

        assertDatabaseHas('package_views', [
            'user_uuid' => $this->user->uuid,
            'package_uuid' => $this->package->uuid,
            'view_count' => 1,
        ]);
    });

    it('increments view count on subsequent views after throttle window', function () {
        $task = app(RecordPackageViewTask::class);

        $task->handle($this->user, $this->package);

        // Move past the throttle window
        PackageView::where('user_uuid', $this->user->uuid)
            ->where('package_uuid', $this->package->uuid)
            ->update(['last_viewed_at' => now()->subMinutes(2)]);

        $task->handle($this->user, $this->package);

        $view = PackageView::where('user_uuid', $this->user->uuid)
            ->where('package_uuid', $this->package->uuid)
            ->first();

        expect($view->view_count)->toBe(2);
    });

    it('does not increment within the throttle window', function () {
        $task = app(RecordPackageViewTask::class);

        $task->handle($this->user, $this->package);
        $task->handle($this->user, $this->package);

        $view = PackageView::where('user_uuid', $this->user->uuid)
            ->where('package_uuid', $this->package->uuid)
            ->first();

        expect($view->view_count)->toBe(1);
    });

    it('tracks views per user independently', function () {
        $otherUser = User::factory()->create();
        $task = app(RecordPackageViewTask::class);

        $task->handle($this->user, $this->package);
        $task->handle($otherUser, $this->package);

        $userView = PackageView::where('user_uuid', $this->user->uuid)->first();
        $otherView = PackageView::where('user_uuid', $otherUser->uuid)->first();

        expect($userView->view_count)->toBe(1);
        expect($otherView->view_count)->toBe(1);
    });
});

describe('package show records views', function () {
    it('records a view when visiting a package page', function () {
        actingAs($this->user)
            ->get(route('organizations.packages.show', [
                $this->organization->slug,
                $this->package->uuid,
            ]));

        assertDatabaseHas('package_views', [
            'user_uuid' => $this->user->uuid,
            'package_uuid' => $this->package->uuid,
            'view_count' => 1,
        ]);
    });

    it('increments view count on repeat visits after throttle window', function () {
        actingAs($this->user)
            ->get(route('organizations.packages.show', [
                $this->organization->slug,
                $this->package->uuid,
            ]));

        // Move past the throttle window
        PackageView::where('user_uuid', $this->user->uuid)
            ->where('package_uuid', $this->package->uuid)
            ->update(['last_viewed_at' => now()->subMinutes(2)]);

        actingAs($this->user)
            ->get(route('organizations.packages.show', [
                $this->organization->slug,
                $this->package->uuid,
            ]));

        $view = PackageView::where('user_uuid', $this->user->uuid)
            ->where('package_uuid', $this->package->uuid)
            ->first();

        expect($view->view_count)->toBe(2);
    });
});

describe('frequent packages on dashboard', function () {
    it('includes frequent packages as a deferred prop', function () {
        $response = actingAs($this->user)
            ->get("/organizations/{$this->organization->slug}");

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('organizations/show')
        );
    });
});
