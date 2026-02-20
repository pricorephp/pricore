<?php

namespace App\Domains\Repository\Actions;

use Illuminate\Support\Facades\File;

class CleanupGitCloneAction
{
    public function handle(?string $clonePath): void
    {
        if ($clonePath && File::isDirectory($clonePath)) {
            File::deleteDirectory($clonePath);
        }
    }
}
