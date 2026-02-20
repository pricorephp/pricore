<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegisterWebhookAction
{
    /**
     * Register a GitHub webhook for the given repository.
     *
     * Returns true if the webhook was registered successfully, false otherwise.
     * Silently returns false for non-GitHub repositories.
     */
    public function handle(Repository $repository): bool
    {
        if ($repository->provider !== GitProvider::GitHub) {
            return false;
        }

        try {
            $provider = GitProviderFactory::make($repository);

            if (! $provider instanceof GitHubProvider) {
                return false;
            }

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
            $callbackUrl = route('webhooks.github', $repository->uuid);

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
}
