<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Models\Repository;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class CreateGitCloneAction
{
    public function handle(Repository $repository): ?string
    {
        if ($repository->provider !== GitProvider::Git) {
            return null;
        }

        $clonePath = storage_path("app/git-clones/{$repository->uuid}");

        if (is_dir($clonePath)) {
            $this->updateClone($clonePath);

            return $clonePath;
        }

        $this->createClone($repository, $clonePath);

        return $clonePath;
    }

    protected function createClone(Repository $repository, string $clonePath): void
    {
        $url = $this->getAuthenticatedUrl($repository);

        $result = Process::env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'clone', '--bare', $url, $clonePath]);

        if ($result->failed()) {
            throw new GitProviderException('Failed to clone repository: '.$result->errorOutput());
        }
    }

    protected function updateClone(string $clonePath): void
    {
        $result = Process::path($clonePath)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'fetch', '--all', '--prune']);

        if ($result->failed()) {
            throw new GitProviderException('Failed to update repository clone: '.$result->errorOutput());
        }
    }

    protected function getAuthenticatedUrl(Repository $repository): string
    {
        $url = $repository->repo_identifier;
        $credentials = $repository->credentials ?? [];

        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;

        if (Str::startsWith($url, ['http://', 'https://']) && $username && $password) {
            $username = urlencode($username);
            $password = urlencode($password);

            $parts = parse_url($url);
            if (! $parts) {
                return $url;
            }

            $scheme = $parts['scheme'] ?? 'https';
            $host = $parts['host'] ?? '';
            $path = $parts['path'] ?? '';
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';

            return "{$scheme}://{$username}:{$password}@{$host}{$port}{$path}";
        }

        return $url;
    }
}
