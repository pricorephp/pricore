<?php

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Security\Actions\MatchAdvisoriesForPackageAction;
use App\Domains\Security\Jobs\ScanPackageVersionsJob;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Support\Facades\Notification;

it('skips queued scans when the organization is deleted or auditing is disabled', function (string $change) {
    Notification::fake();
    $organization = Organization::factory()->create();
    $package = Package::factory()->withoutRepository()->forOrganization($organization)->create();
    $payload = serialize(new ScanPackageVersionsJob($package->load('organization')));

    if ($change === 'deleted') {
        $organization->delete();
    } else {
        $organization->update(['security_audits_enabled' => false]);
    }

    $this->mock(MatchAdvisoriesForPackageAction::class)->shouldNotReceive('handle');
    $this->mock(RecordActivityTask::class)->shouldNotReceive('handle');

    app()->call([unserialize($payload), 'handle']);

    Notification::assertNothingSent();
})->with(['deleted', 'disabled']);

it('scans packages for active organizations with auditing enabled', function () {
    $organization = Organization::factory()->create(['security_audits_enabled' => true]);
    $package = Package::factory()->withoutRepository()->forOrganization($organization)->create();

    $this->mock(MatchAdvisoriesForPackageAction::class)
        ->shouldReceive('handle')->once()->withArgs(fn (Package $scanned) => $scanned->is($package))->andReturn(0);
    $this->mock(RecordActivityTask::class)->shouldNotReceive('handle');

    ScanPackageVersionsJob::dispatchSync($package);
});
