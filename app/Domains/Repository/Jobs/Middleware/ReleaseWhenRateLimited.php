<?php

namespace App\Domains\Repository\Jobs\Middleware;

use App\Domains\Repository\Exceptions\GitProviderRateLimitedException;
use App\Domains\Repository\Jobs\SyncRefJob;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Puts a job back on the queue until the provider's rate limit resets instead
 * of sleeping in the worker. The first job to hit the limit records when it
 * resets, so the other jobs for the repository wait without making requests.
 */
class ReleaseWhenRateLimited
{
    public function __construct(
        protected string $repositoryUuid,
    ) {}

    public function handle(SyncRefJob $job, Closure $next): void
    {
        $key = "git-provider-rate-limit:{$this->repositoryUuid}";
        $resetsAt = Cache::get($key);

        if (is_int($resetsAt) && $resetsAt > now()->getTimestamp()) {
            $job->release($resetsAt - now()->getTimestamp());

            return;
        }

        try {
            $next($job);
        } catch (Throwable $e) {
            $rateLimit = GitProviderRateLimitedException::find($e);

            if ($rateLimit === null) {
                throw $e;
            }

            Cache::put($key, now()->getTimestamp() + $rateLimit->retryAfter, $rateLimit->retryAfter);

            $job->release($rateLimit->retryAfter);
        }
    }
}
