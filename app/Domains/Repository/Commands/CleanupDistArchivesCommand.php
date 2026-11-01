<?php

namespace App\Domains\Repository\Commands;

use App\Domains\Repository\Actions\CleanupDistArchivesAction;
use Illuminate\Console\Command;

class CleanupDistArchivesCommand extends Command
{
    protected $signature = 'dist:cleanup';

    protected $description = 'Remove old dist archives based on per-package retention settings';

    public function handle(CleanupDistArchivesAction $action): int
    {
        $result = $action->handle();

        $this->components->info(
            "Cleaned up {$result['archives_removed']} archives across {$result['packages']} packages."
        );

        if ($result['detached_marked'] > 0) {
            $this->components->info(
                "Marked {$result['detached_marked']} archives detached from a moved reference."
            );
        }

        $this->components->info(
            "Removed {$result['detached_removed']} detached archives past the retention window."
        );

        return self::SUCCESS;
    }
}
