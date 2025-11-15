<?php

namespace App\Domains\Repository\Commands;

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Laravel\Prompts\spin;

class SyncRepositoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:repository
                            {repository? : The UUID of the repository to sync}
                            {--organization= : Sync all repositories for this organization (UUID or slug)}
                            {--all : Sync all repositories}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync packages from Git repositories';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $repositories = $this->getRepositories();

        if ($repositories->isEmpty()) {
            $this->error('No repositories found to sync.');

            return self::FAILURE;
        }

        $this->info("Found {$repositories->count()} repository/repositories to sync.");

        spin(
            callback: function () use ($repositories) {
                foreach ($repositories as $repository) {
                    SyncRepositoryJob::dispatchSync($repository);
                }
            },
            message: 'Dispatching sync jobs...',
        );

        $this->info('All sync jobs have been dispatched successfully.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Repository>
     */
    protected function getRepositories(): Collection
    {
        if ($this->option('all')) {
            return Repository::all();
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

            /** @var Collection<int, Repository> */
            return $organization->repositories;
        }

        if ($repositoryUuid = $this->argument('repository')) {
            $repository = Repository::find($repositoryUuid);

            if (! $repository) {
                $this->error("Repository '{$repositoryUuid}' not found.");

                return collect();
            }

            /** @var Collection<int, Repository> */
            return collect([$repository]);
        }

        $this->error('Please specify a repository, organization, or use --all flag.');

        return collect();
    }
}
