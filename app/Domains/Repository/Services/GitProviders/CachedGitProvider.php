<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Process;

class CachedGitProvider implements GitProviderInterface
{
    public function __construct(
        protected string $clonePath,
        protected string $repositoryIdentifier,
    ) {}

    public function getTags(): array
    {
        throw new GitProviderException('CachedGitProvider does not support getTags. Use GenericGitProvider for collection.');
    }

    public function getBranches(): array
    {
        throw new GitProviderException('CachedGitProvider does not support getBranches. Use GenericGitProvider for collection.');
    }

    public function getFileContent(string $ref, string $path): ?string
    {
        $result = Process::path($this->clonePath)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'show', "{$ref}:{$path}"]);

        if ($result->failed()) {
            return null;
        }

        return $result->output();
    }

    public function getCommitDate(string $ref): ?CarbonImmutable
    {
        $result = Process::path($this->clonePath)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'show', '-s', '--format=%cI', $ref]);

        if ($result->failed()) {
            return null;
        }

        $date = trim($result->output());

        if ($date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (\Exception) {
            return null;
        }
    }

    public function validateCredentials(): bool
    {
        return is_dir($this->clonePath);
    }

    public function getRepositoryIdentifier(): string
    {
        return $this->repositoryIdentifier;
    }

    public function getRepositoryUrl(): string
    {
        return $this->repositoryIdentifier;
    }

    public function createWebhook(string $url, string $secret): array
    {
        throw new GitProviderException('CachedGitProvider does not support webhooks.');
    }

    public function deleteWebhook(int|string $hookId): void
    {
        throw new GitProviderException('CachedGitProvider does not support webhooks.');
    }

    public function downloadArchive(string $ref, string $outputPath): bool
    {
        $result = Process::path($this->clonePath)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'archive', '--format=zip', "--output={$outputPath}", $ref]);

        return $result->successful() && file_exists($outputPath);
    }
}
