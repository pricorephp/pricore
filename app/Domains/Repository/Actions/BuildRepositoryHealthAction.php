<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Data\RecentSyncData;
use App\Domains\Repository\Contracts\Data\RepositoryHealthData;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\RepositorySyncLog;
use Illuminate\Support\Collection;

class BuildRepositoryHealthAction
{
    public const int RECENT_SYNCS_LIMIT = 10;

    /**
     * @return Collection<int, RepositoryHealthData>
     */
    public function handle(Organization $organization): Collection
    {
        $repositories = $organization->repositories()
            ->withCount('packages')
            ->orderBy('name')
            ->get();

        $recentSyncs = $this->recentSyncsByRepository($repositories->pluck('uuid')->all());

        return $repositories
            ->map(fn (Repository $repository) => new RepositoryHealthData(
                uuid: $repository->uuid,
                name: $repository->name,
                provider: $repository->provider->value,
                repoIdentifier: $repository->repo_identifier,
                syncStatus: $repository->sync_status,
                syncStatusLabel: $repository->sync_status?->label(),
                lastSyncedAt: $repository->last_synced_at,
                packagesCount: $repository->packages_count ?? 0,
                recentSyncs: $recentSyncs->get($repository->uuid, collect())->values()->all(),
            ))
            ->sortBy(fn (RepositoryHealthData $repository) => match ($repository->syncStatus) {
                RepositorySyncStatus::Failed => 0,
                RepositorySyncStatus::Pending, null => 1,
                RepositorySyncStatus::Ok => 2,
            })
            ->values();
    }

    /**
     * @param  array<int, string>  $repositoryUuids
     * @return Collection<string, Collection<int, RecentSyncData>>
     */
    protected function recentSyncsByRepository(array $repositoryUuids): Collection
    {
        if ($repositoryUuids === []) {
            return collect();
        }

        $ranked = RepositorySyncLog::query()
            ->select(['uuid', 'repository_uuid', 'status', 'started_at'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY repository_uuid ORDER BY started_at DESC) as sync_rank')
            ->whereIn('repository_uuid', $repositoryUuids);

        /** @var Collection<string, Collection<int, RecentSyncData>> $recentSyncs */
        $recentSyncs = RepositorySyncLog::query()
            ->fromSub($ranked, 'repository_sync_logs')
            ->where('sync_rank', '<=', static::RECENT_SYNCS_LIMIT)
            ->orderBy('started_at', 'desc')
            ->get()
            ->groupBy('repository_uuid')
            ->map(fn (Collection $syncLogs) => $syncLogs->map(fn (RepositorySyncLog $syncLog) => new RecentSyncData(
                uuid: $syncLog->uuid,
                status: $syncLog->status,
                statusLabel: $syncLog->status->label(),
                startedAt: $syncLog->started_at,
            )));

        return $recentSyncs;
    }
}
