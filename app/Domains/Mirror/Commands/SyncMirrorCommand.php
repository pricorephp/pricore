<?php

namespace App\Domains\Mirror\Commands;

use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Models\Mirror;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\spin;

class SyncMirrorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sync:mirror
                            {mirror? : The UUID of the mirror to sync}
                            {--organization= : Sync all mirrors for this organization (UUID or slug)}
                            {--all : Sync all mirrors}';

    /**
     * @var string
     */
    protected $description = 'Sync packages from external registry mirrors';

    public function handle(): int
    {
        $mirrors = $this->getMirrors();

        if ($mirrors->isEmpty()) {
            $this->error('No mirrors found to sync.');

            return self::FAILURE;
        }

        $this->info("Found {$mirrors->count()} mirror(s) to sync.");

        spin(
            callback: function () use ($mirrors) {
                foreach ($mirrors as $mirror) {
                    SyncMirrorJob::dispatchSync($mirror);
                }
            },
            message: 'Dispatching sync jobs...',
        );

        $this->info('All sync jobs have been dispatched successfully.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Mirror>
     */
    protected function getMirrors(): Collection
    {
        if ($this->option('all')) {
            return Mirror::all();
        }

        if ($organizationIdentifier = $this->option('organization')) {
            $organization = Organization::query()
                ->where('uuid', $organizationIdentifier)
                ->orWhere('slug', $organizationIdentifier)
                ->first();

            if (! $organization) {
                $this->error("Organization '{$organizationIdentifier}' not found.");

                return collect();
            }

            /** @var Collection<int, Mirror> */
            return $organization->mirrors;
        }

        if ($mirrorUuid = $this->argument('mirror')) {
            $mirror = Mirror::find($mirrorUuid);

            if (! $mirror) {
                $this->error("Mirror '{$mirrorUuid}' not found.");

                return collect();
            }

            /** @var Collection<int, Mirror> */
            return collect([$mirror]);
        }

        $this->error('Please specify a mirror, organization, or use --all flag.');

        return collect();
    }
}
