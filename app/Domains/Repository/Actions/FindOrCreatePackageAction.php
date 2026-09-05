<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Activity\Actions\RecordActivityTask;
use App\Domains\Activity\Contracts\Enums\ActivityType;
use App\Models\Package;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

class FindOrCreatePackageAction
{
    public function __construct(
        protected RecordActivityTask $recordActivityTask,
    ) {}

    /**
     * Returns null when the name already belongs to a package of another
     * repository or a mirror: attaching this repository's versions to it would
     * silently mix two sources under one name.
     */
    public function handle(Repository $repository, string $packageName, string $sourcePath = ''): ?Package
    {
        $sourcePath = $sourcePath === '' ? null : $sourcePath;

        $package = Package::query()->firstOrCreate([
            'organization_uuid' => $repository->organization_uuid,
            'name' => $packageName,
        ], [
            'repository_uuid' => $repository->uuid,
            'source_path' => $sourcePath,
            'type' => 'library',
            'visibility' => 'private',
        ]);

        if ($package->wasRecentlyCreated) {
            $this->recordCreated($repository, $package);

            return $package;
        }

        $ownedElsewhere = $package->mirror_uuid !== null
            || ($package->repository_uuid !== null && $package->repository_uuid !== $repository->uuid);

        if ($ownedElsewhere) {
            Log::warning('Package name is already used by another source', [
                'repository' => $repository->name,
                'package' => $packageName,
                'owner_repository_uuid' => $package->repository_uuid,
                'owner_mirror_uuid' => $package->mirror_uuid,
            ]);

            return null;
        }

        $changes = [];

        if ($package->repository_uuid === null) {
            $changes['repository_uuid'] = $repository->uuid;
        }

        if ($package->source_path !== $sourcePath) {
            $changes['source_path'] = $sourcePath;
        }

        if ($changes !== []) {
            $package->update($changes);
        }

        return $package;
    }

    protected function recordCreated(Repository $repository, Package $package): void
    {
        $organization = $repository->organization()->first();

        if (! $organization) {
            return;
        }

        $this->recordActivityTask->handle(
            organization: $organization,
            type: ActivityType::PackageCreated,
            subject: $package,
            properties: [
                'name' => $package->name,
                'repository_name' => $repository->name,
                'source_path' => $package->source_path,
            ],
        );
    }
}
