<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Domains\Repository\Jobs\SyncRepositoryJob;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class SyncRepositoryController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Organization $organization, Repository $repository): RedirectResponse
    {
        $this->authorize('deleteRepository', $organization);

        $repository->update(['sync_status' => RepositorySyncStatus::Pending]);

        SyncRepositoryJob::dispatch($repository);

        return redirect()
            ->back()
            ->with('status', 'Repository sync has started.');
    }
}
