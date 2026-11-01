<?php

namespace App\Domains\Repository\Actions;

use App\Models\Repository;
use App\Models\RepositoryView;
use App\Models\User;

class RecordRepositoryViewTask
{
    public function handle(User $user, Repository $repository): void
    {
        $view = RepositoryView::firstOrNew([
            'user_uuid' => $user->uuid,
            'repository_uuid' => $repository->uuid,
        ]);

        if ($view->exists && $view->last_viewed_at->isAfter(now()->subMinute())) {
            return;
        }

        $view->view_count = ($view->view_count ?? 0) + 1;
        $view->last_viewed_at = now();
        $view->save();
    }
}
