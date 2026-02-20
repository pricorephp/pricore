<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

class DeleteWebhookAction
{
    /**
     * Delete the GitHub webhook for the given repository.
     */
    public function handle(Repository $repository): void
    {
        if ($repository->provider !== GitProvider::GitHub || ! $repository->webhook_id) {
            return;
        }

        try {
            $provider = GitProviderFactory::make($repository);

            if ($provider instanceof GitHubProvider) {
                $provider->deleteWebhook((int) $repository->webhook_id);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete webhook from GitHub', [
                'repository' => $repository->uuid,
                'webhook_id' => $repository->webhook_id,
                'error' => $e->getMessage(),
            ]);
        }

        $repository->update([
            'webhook_id' => null,
            'webhook_secret' => null,
        ]);
    }
}
