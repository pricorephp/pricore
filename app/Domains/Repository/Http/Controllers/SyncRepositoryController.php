<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Http\RedirectResponse;

class SyncRepositoryController extends Controller
{
    public function __invoke(Organization $organization, Repository $repository): RedirectResponse
    {
        SyncRepositoryJob::dispatch($repository);

        return redirect()
            ->back()
            ->with('success', 'Repository sync has been queued.');
    }
}
