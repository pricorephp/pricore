<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Exceptions\GitProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class GenericGitProvider extends AbstractGitProvider
{
    protected function configureHttpClient(): PendingRequest
    {
        // Not used for Generic Git, but required by abstract class
        return Http::withHeaders([]);
    }

    /**
     * @return array<int, array{name: string, commit: string}>
     */
    public function getTags(): array
    {
        $output = $this->runGitCommand(['ls-remote', '--tags', '--refs', $this->getAuthenticatedUrl()]);

        return $this->parseLsRemoteOutput($output, 'refs/tags/');
    }

    /**
     * @return array<int, array{name: string, commit: string}>
     */
    public function getBranches(): array
    {
        $output = $this->runGitCommand(['ls-remote', '--heads', $this->getAuthenticatedUrl()]);

        return $this->parseLsRemoteOutput($output, 'refs/heads/');
    }

    public function getFileContent(string $ref, string $path): ?string
    {
        $tempDir = sys_get_temp_dir().'/pricore-git-'.Str::random(16);

        try {
            if (! mkdir($tempDir, 0755, true)) {
                throw new GitProviderException('Failed to create temporary directory');
            }

            // Try to shallow clone the specific ref
            // Note: --branch works for both tags and branches
            $this->runGitCommand([
                'clone',
                '--depth', '1',
                '--branch', $ref,
                $this->getAuthenticatedUrl(),
                $tempDir,
            ]);

            $filePath = $tempDir.'/'.ltrim($path, '/');

            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);

                return $content === false ? null : $content;
            }

            return null;
        } catch (\Exception $e) {
            // If clone fails (e.g. ref doesn't exist or is a commit SHA not reachable by branch name)
            // We could try to fallback to fetching by SHA if needed, but for now this covers standard use cases.
            return null;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function validateCredentials(): bool
    {
        try {
            $this->runGitCommand(['ls-remote', $this->getAuthenticatedUrl(), 'HEAD']);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRepositoryUrl(): string
    {
        return $this->repositoryIdentifier;
    }

    protected function getAuthenticatedUrl(): string
    {
        $url = $this->repositoryIdentifier;

        // Basic check if URL is http(s) and we have credentials
        if (Str::startsWith($url, ['http://', 'https://']) && $this->hasCredential('username') && $this->hasCredential('password')) {
            $username = urlencode($this->getCredential('username'));
            $password = urlencode($this->getCredential('password'));

            // Parse URL to inject credentials
            $parts = parse_url($url);
            if (! $parts) {
                return $url;
            }

            $scheme = $parts['scheme'] ?? 'https';
            $host = $parts['host'] ?? '';
            $path = $parts['path'] ?? '';
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';
            $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

            // Handle port if present
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';

            return "{$scheme}://{$username}:{$password}@{$host}{$port}{$path}{$query}{$fragment}";
        }

        return $url;
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function runGitCommand(array $command): string
    {
        $env = [
            'GIT_TERMINAL_PROMPT' => '0',
        ];

        $result = Process::env($env)->run(array_merge(['git'], $command));

        if ($result->failed()) {
            throw new GitProviderException('Git command failed: '.$result->errorOutput());
        }

        return trim($result->output());
    }

    /**
     * @return array<int, array{name: string, commit: string}>
     */
    protected function parseLsRemoteOutput(string $output, string $prefix): array
    {
        $lines = explode("\n", $output);
        $refs = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));

            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $sha = $parts[0];
            $refName = $parts[1];

            if (Str::startsWith($refName, $prefix)) {
                $name = substr($refName, strlen($prefix));

                // Filter out dereferenced tags (ending in ^{})
                if (Str::endsWith($name, '^{}')) {
                    continue;
                }

                $refs[] = [
                    'name' => $name,
                    'commit' => $sha,
                ];
            }
        }

        return $refs;
    }
}
