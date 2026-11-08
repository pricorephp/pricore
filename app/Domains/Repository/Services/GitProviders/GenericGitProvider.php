<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Rules\ValidRepositoryIdentifier;
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
        $output = $this->runGitCommand(['ls-remote', '--tags', '--refs', '--', $this->getAuthenticatedUrl()]);

        return $this->parseLsRemoteOutput($output, 'refs/tags/');
    }

    /**
     * @return array<int, array{name: string, commit: string}>
     */
    public function getBranches(): array
    {
        $output = $this->runGitCommand(['ls-remote', '--heads', '--', $this->getAuthenticatedUrl()]);

        return $this->parseLsRemoteOutput($output, 'refs/heads/');
    }

    public function getFileContent(string $ref, string $path): ?string
    {
        return $this->withShallowClone($ref, function (string $tempDir) use ($path): ?string {
            $filePath = $tempDir.'/'.ltrim($path, '/');

            if (! file_exists($filePath)) {
                return null;
            }

            $content = file_get_contents($filePath);

            return $content === false ? null : $content;
        });
    }

    /**
     * @return array<int, array{name: string, type: 'dir'|'file'}>
     */
    public function listDirectory(string $ref, string $path): array
    {
        $entries = $this->withShallowClone($ref, function (string $tempDir) use ($path): array {
            $path = trim($path, '/');
            $directory = $path === '' ? $tempDir : "{$tempDir}/{$path}";

            if (! is_dir($directory)) {
                return [];
            }

            $entries = [];

            foreach (scandir($directory) ?: [] as $name) {
                // The clone's own metadata is not part of the repository tree
                if ($name === '.' || $name === '..' || ($path === '' && $name === '.git')) {
                    continue;
                }

                $entries[] = [
                    'name' => $name,
                    'type' => is_dir("{$directory}/{$name}") ? 'dir' : 'file',
                ];
            }

            return $entries;
        });

        return $entries ?? [];
    }

    /**
     * Run $callback against a fresh shallow clone of $ref, removed afterwards.
     * Returns null when the ref cannot be cloned (unknown ref, or a commit SHA
     * not reachable by name).
     *
     * @template TResult
     *
     * @param  callable(string): TResult  $callback
     * @return TResult|null
     */
    protected function withShallowClone(string $ref, callable $callback): mixed
    {
        $tempDir = sys_get_temp_dir().'/pricore-git-'.Str::random(16);

        try {
            if (! mkdir($tempDir, 0755, true)) {
                throw new GitProviderException('Failed to create temporary directory');
            }

            // Note: --branch works for both tags and branches
            $this->runGitCommand([
                'clone',
                '--depth', '1',
                '--branch', $ref,
                '--',
                $this->getAuthenticatedUrl(),
                $tempDir,
            ]);

            return $callback($tempDir);
        } catch (\Exception $e) {
            return null;
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function ensureRepositoryAccess(): void
    {
        $redactedUrl = static::redactUrlCredentials($this->repositoryIdentifier);

        try {
            $this->runGitCommand(['ls-remote', '--', $this->getAuthenticatedUrl(), 'HEAD']);
        } catch (GitProviderException $e) {
            throw new GitProviderException(
                "Could not read {$redactedUrl}. Check the URL and, for a private repository, that the selected SSH key is added as a deploy key.\n\n".trim($e->getMessage()),
                previous: $e,
            );
        } catch (\Exception $e) {
            throw new GitProviderException(
                "Could not read {$redactedUrl}: git did not finish. Check that the host is reachable from Pricore.",
                previous: $e,
            );
        }
    }

    /**
     * A remote cannot be asked for a commit by SHA without fetching it, so this
     * settles for the repository still being readable.
     */
    public function ensureRefResolves(string $ref): void
    {
        $this->ensureRepositoryAccess();
    }

    public function getRepositoryUrl(): string
    {
        return $this->repositoryIdentifier;
    }

    public function createWebhook(string $url, string $secret): array
    {
        throw new GitProviderException('Generic Git provider does not support webhooks.');
    }

    public function deleteWebhook(int|string $hookId): void
    {
        throw new GitProviderException('Generic Git provider does not support webhooks.');
    }

    protected function downloadFullArchive(string $ref, string $outputPath): bool
    {
        return false;
    }

    protected function getAuthenticatedUrl(): string
    {
        $url = $this->repositoryIdentifier;

        // Never hand git an address it could read as a transport helper, a local path,
        // or an option. Request validation covers new repositories; this covers rows
        // stored before that validation existed, or written by any other path.
        if (! ValidRepositoryIdentifier::passes($url, GitProvider::Git)) {
            throw new GitProviderException('Refusing to use an unsupported repository URL.');
        }

        // SSH URLs are passed through unchanged — auth is handled via GIT_SSH_COMMAND
        if ($this->hasCredential('ssh_key') || ! Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        // The URL is guaranteed http(s) here, so only credentials remain to check
        if ($this->hasCredential('username') && $this->hasCredential('password')) {
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
     * Messages end up in sync logs that every organization member can read. Over
     * http(s) a token may sit in the username, so the whole userinfo goes; over
     * other transports the username is not secret, as in ssh://git@host.
     */
    public static function redactUrlCredentials(string $text): string
    {
        $text = (string) preg_replace('#(https?://)[^/\s]+@#i', '$1***@', $text);

        return (string) preg_replace('#(\w+://[^/\s:@]*):[^/\s]+@#', '$1:***@', $text);
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function runGitCommand(array $command): string
    {
        $env = [
            'GIT_TERMINAL_PROMPT' => '0',
        ];

        $tempKeyFile = null;

        if ($this->hasCredential('ssh_key')) {
            $tempKeyFile = sys_get_temp_dir().'/pricore-ssh-'.Str::random(16);
            file_put_contents($tempKeyFile, $this->getCredential('ssh_key')."\n");
            chmod($tempKeyFile, 0600);
            $env['GIT_SSH_COMMAND'] = "ssh -i {$tempKeyFile} -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes";
        }

        try {
            $result = Process::env($env)->run(array_merge(['git'], $command));

            if ($result->failed()) {
                throw new GitProviderException('Git command failed: '.static::redactUrlCredentials($result->errorOutput()));
            }

            return trim($result->output());
        } finally {
            if ($tempKeyFile !== null && file_exists($tempKeyFile)) {
                unlink($tempKeyFile);
            }
        }
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
