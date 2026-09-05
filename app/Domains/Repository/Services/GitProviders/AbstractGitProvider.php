<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Services\Archive\ZipSubtreeExtractor;
use Illuminate\Http\Client\PendingRequest;
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
