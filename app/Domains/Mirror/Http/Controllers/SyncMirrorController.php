<?php

namespace App\Domains\Mirror\Http\Controllers;

use App\Domains\Mirror\Jobs\SyncMirrorJob;
use App\Domains\Repository\Contracts\Enums\RepositorySyncStatus;
use App\Models\Mirror;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class SyncMirrorController
{
    use AuthorizesRequests;

    public function __invoke(Organization $organization, Mirror $mirror): RedirectResponse
    {
        $this->authorize('viewSettings', $organization);

        $mirror->update(['sync_status' => RepositorySyncStatus::Pending]);

        SyncMirrorJob::dispatch($mirror);

        return redirect()
            ->back()
            ->with('status', 'Mirror sync has started.');
    }
}
