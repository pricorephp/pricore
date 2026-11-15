<?php

namespace App\Domains\Mirror\Jobs;

use App\Domains\Mirror\Actions\ImportMirrorPackageAction;
use App\Models\Package;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshMirrorPackageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function __construct(
        public Package $package,
    ) {}

    public function uniqueId(): string
    {
        return "refresh-mirror-package-{$this->package->uuid}";
    }

    public function handle(ImportMirrorPackageAction $importMirrorPackageAction): void
    {
        $mirror = $this->package->mirror;

        if (! $mirror || ! $mirror->isOnDemand()) {
            return;
        }

        $importMirrorPackageAction->handle($mirror, $this->package->name);
    }
}
