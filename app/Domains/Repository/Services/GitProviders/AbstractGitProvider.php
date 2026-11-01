<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderRateLimitedException;
use App\Domains\Repository\Services\Archive\ZipSubtreeExtractor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class AbstractGitProvider implements GitProviderInterface
{
    protected PendingRequest $http;

    /**
     * Whole-repository archives this instance already downloaded, keyed by ref.
     * A sync builds one provider per ref, so every package cut from that ref
     * shares a single download.
     *
     * @var array<string, string>
     */
    protected array $fullArchives = [];

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        protected string $repositoryIdentifier,
        protected array $credentials
    ) {
        $this->http = $this->configureHttpClient();
    }

    public function __destruct()
    {
        foreach ($this->fullArchives as $tempPath) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Configure the HTTP client with authentication and base URL.
     */
    abstract protected function configureHttpClient(): PendingRequest;

    /**
     * Retry server errors with backoff. A rate limit is not waited out here: it
     * can last far longer than a worker may run, so it is thrown for the job to
     * be released until the limit resets.
     */
    protected function withRetries(PendingRequest $http): PendingRequest
    {
        return $http->retry(
            times: 3,
            // Exponential backoff: 1s, 5s
            sleepMilliseconds: fn (int $attempt) => (int) (1000 * pow(5, $attempt - 1)),
            when: function (\Throwable $exception): bool {
                $response = $exception instanceof RequestException ? $exception->response : null;

                if (! $response) {
                    return false;
                }

                $retryAfter = $this->rateLimitRetryAfter($response);

                if ($retryAfter !== null) {
                    Log::warning('Git provider rate limit hit', [
                        'provider' => class_basename($this),
                        'repository' => $this->repositoryIdentifier,
                        'retry_after' => $retryAfter,
                    ]);

                    throw new GitProviderRateLimitedException($retryAfter);
                }

                return $response->serverError();
            },
            throw: false,
        );
    }

    /**
     * Seconds until the provider accepts requests again, or null when the
     * response is not a rate limit.
     */
    protected function rateLimitRetryAfter(Response $response): ?int
    {
        if ($response->status() !== 429) {
            return null;
        }

        return max(1, (int) ($response->header('Retry-After') ?: 60));
    }

    /**
     * Download the whole repository at $ref. Provider archive endpoints have no
     * path filter, so subdirectory archives are cut from this locally.
     */
    abstract protected function downloadFullArchive(string $ref, string $outputPath): bool;

    public function downloadArchive(string $ref, string $outputPath, ?string $path = null): bool
    {
        $path = trim((string) $path, '/');

        if ($path === '') {
            return $this->downloadFullArchive($ref, $outputPath);
        }

        $fullArchive = $this->fullArchive($ref);

        if ($fullArchive === null) {
            return false;
        }

        return (new ZipSubtreeExtractor)->extract(
            $fullArchive,
            $outputPath,
            $path,
            ZipSubtreeExtractor::prefixFor($path, $ref),
        );
    }

    protected function fullArchive(string $ref): ?string
    {
        $cached = $this->fullArchives[$ref] ?? null;

        if ($cached !== null && file_exists($cached)) {
            return $cached;
        }

        $tempPath = sys_get_temp_dir().'/pricore-archive-'.Str::random(16).'.zip';

        if (! $this->downloadFullArchive($ref, $tempPath)) {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            return null;
        }

        $this->fullArchives[$ref] = $tempPath;

        return $tempPath;
    }

    public function getRepositoryIdentifier(): string
    {
        return $this->repositoryIdentifier;
    }

    protected function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    protected function hasCredential(string $key): bool
    {
        return isset($this->credentials[$key]);
    }
}
