<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Actions\RequestRepositorySyncAction;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class SyncRepositoryController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Organization $organization, Repository $repository, RequestRepositorySyncAction $requestRepositorySyncAction): RedirectResponse
    {
        $this->authorize('deleteRepository', $organization);

        $requestRepositorySyncAction->handle($repository);

        return redirect()
            ->back()
            ->with('status', 'Repository sync has started.');
    }
}
