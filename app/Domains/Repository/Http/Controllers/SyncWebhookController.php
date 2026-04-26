<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Actions\RegisterWebhookAction;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

class SyncWebhookController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(
        Organization $organization,
        Repository $repository,
        RegisterWebhookAction $registerWebhookAction,
    ): RedirectResponse {
        $this->authorize('deleteRepository', $organization);

        $success = $registerWebhookAction->handle($repository);

        if ($success) {
            return redirect()
                ->back()
                ->with('status', 'Webhook registered successfully.');
        }

        return redirect()
            ->back()
            ->with('error', 'Failed to register webhook. Check the repository credentials and try again.');
    }
}
