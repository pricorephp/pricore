<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;
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
}
