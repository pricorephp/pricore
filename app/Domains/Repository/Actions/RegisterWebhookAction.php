<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegisterWebhookAction
{
    /**
     * Register a webhook for the given repository.
     *
     * Returns true if the webhook was registered successfully, false otherwise.
     * Silently returns false for providers that don't support webhooks.
     */
    public function handle(Repository $repository): bool
    {
        if (! $repository->provider->supportsWebhooks()) {
            return false;
        }

        if (! $repository->provider->supportsAutomaticWebhooks()) {
            return $this->activateManualWebhook($repository);
        }

        try {
            $provider = GitProviderFactory::make($repository);

            // Delete existing webhook if present
            if ($repository->webhook_id) {
                try {
                    $provider->deleteWebhook((int) $repository->webhook_id);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete old webhook, continuing with registration', [
                        'repository' => $repository->uuid,
                        'webhook_id' => $repository->webhook_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $secret = Str::random(40);
            $callbackUrl = route($repository->provider->webhookRouteName(), $repository->uuid);

            $result = $provider->createWebhook($callbackUrl, $secret);

            $repository->update([
                'webhook_id' => (string) $result['id'],
                'webhook_secret' => $secret,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to register webhook', [
                'repository' => $repository->uuid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function activateManualWebhook(Repository $repository): bool
    {
        $repository->update([
            'webhook_id' => 'manual',
            'webhook_secret' => Str::random(40),
        ]);

        return true;
    }
}
