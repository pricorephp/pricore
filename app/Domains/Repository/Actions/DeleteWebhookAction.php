<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;

class DeleteWebhookAction
{
    public function handle(Repository $repository): void
    {
        if (! $repository->provider->supportsWebhooks() || ! $repository->webhook_id) {
            return;
        }

        try {
            $provider = GitProviderFactory::make($repository);
            $provider->deleteWebhook((int) $repository->webhook_id);
        } catch (\Exception $e) {
            Log::warning('Failed to delete webhook', [
                'repository' => $repository->uuid,
                'provider' => $repository->provider->value,
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
