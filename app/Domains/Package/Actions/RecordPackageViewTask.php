<?php

namespace App\Domains\Package\Actions;

use App\Models\Package;
use App\Models\PackageView;
use App\Models\User;

class RecordPackageViewTask
{
    public function handle(User $user, Package $package): void
    {
        $view = PackageView::firstOrNew([
            'user_uuid' => $user->uuid,
            'package_uuid' => $package->uuid,
        ]);

        if ($view->exists && $view->last_viewed_at->isAfter(now()->subMinute())) {
            return;
        }

        $view->view_count = ($view->view_count ?? 0) + 1;
        $view->last_viewed_at = now();
        $view->save();
    }
}
