<?php

namespace App\Domains\Repository\Commands;

use App\Domains\Repository\Actions\FetchReadmeAction;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\PackageVersion;
use App\Models\Repository;
use Illuminate\Console\Command;

class BackfillReadmesCommand extends Command
{
    protected $signature = 'pricore:backfill-readmes
                            {--repository= : Limit to a single repository UUID}
                            {--chunk=50 : Number of versions to load per chunk}';

    protected $description = 'Fetch and store README files for already-synced package versions that do not have one yet.';

    public function handle(FetchReadmeAction $fetchReadmeAction): int
    {
        $repositoryFilter = $this->option('repository');

        $query = PackageVersion::query()
            ->whereNull('readme')
            ->whereHas('package.repository', function ($query) use ($repositoryFilter) {
                if ($repositoryFilter) {
                    $query->where('uuid', $repositoryFilter);
                }
            })
            ->with(['package.repository']);

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No package versions need a README backfill.');

            return self::SUCCESS;
        }

        $this->info("Backfilling README for {$total} version(s)...");

        $providers = [];
        $fetched = 0;
        $missing = 0;

        $query->chunkById((int) $this->option('chunk'), function ($versions) use (
            $fetchReadmeAction,
            &$providers,
            &$fetched,
            &$missing,
        ) {
            foreach ($versions as $version) {
                /** @var Repository $repository */
                $repository = $version->package->repository;

                $providers[$repository->uuid] ??= GitProviderFactory::make($repository);

                $ref = $version->source_tag ?: $version->source_reference;

                if (! $ref) {
                    $missing++;

                    continue;
                }

                $readme = $fetchReadmeAction->handle($providers[$repository->uuid], $ref);

                if ($readme === null) {
                    $missing++;

                    continue;
                }

                $version->update(['readme' => $readme]);
                $fetched++;
            }
        });

        $this->info("Done. Fetched: {$fetched}. Skipped (no README found): {$missing}.");

        return self::SUCCESS;
    }
}
