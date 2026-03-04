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

        return self::SUCCESS;
    }
}
