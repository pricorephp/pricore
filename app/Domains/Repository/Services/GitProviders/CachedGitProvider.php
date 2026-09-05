<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Interfaces\GitProviderInterface;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\Archive\ZipSubtreeExtractor;
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
        if (! $this->isUsableRef($ref)) {
            return null;
        }

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

    public function createWebhook(string $url, string $secret): array
    {
        throw new GitProviderException('CachedGitProvider does not support webhooks.');
    }

    public function deleteWebhook(int|string $hookId): void
    {
        throw new GitProviderException('CachedGitProvider does not support webhooks.');
    }

    /**
     * @return array<int, array{name: string, type: 'dir'|'file'}>
     */
    public function listDirectory(string $ref, string $path): array
    {
        if (! $this->isUsableRef($ref)) {
            return [];
        }

        $path = trim($path, '/');

        $result = Process::path($this->clonePath)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run(['git', 'ls-tree', '-z', $path === '' ? $ref : "{$ref}:{$path}"]);

        if ($result->failed()) {
            return [];
        }

        $entries = [];

        // Each record reads "<mode> <type> <object>\t<name>", NUL-terminated
        foreach (explode("\0", $result->output()) as $record) {
            $tab = strpos($record, "\t");

            if ($tab === false) {
                continue;
            }

            $type = explode(' ', substr($record, 0, $tab))[1] ?? '';

            $entries[] = [
                'name' => substr($record, $tab + 1),
                'type' => $type === 'tree' ? 'dir' : 'file',
            ];
        }

        return $entries;
    }

    public function downloadArchive(string $ref, string $outputPath, ?string $path = null): bool
    {
        if (! $this->isUsableRef($ref)) {
            return false;
        }

        $path = trim((string) $path, '/');

        $command = ['git', 'archive', '--format=zip', "--output={$outputPath}"];

        if ($path === '') {
            $command[] = $ref;
        } else {
            // "<ref>:<path>" names the subdirectory's tree, so the archive is rooted there
            $command[] = '--prefix='.ZipSubtreeExtractor::prefixFor($path, $ref).'/';
            $command[] = "{$ref}:{$path}";
        }

        $result = Process::path($this->clonePath)
            ->env(['GIT_TERMINAL_PROMPT' => '0'])
            ->run($command);

        return $result->successful() && file_exists($outputPath);
    }

    /**
     * None of `git show`, `git ls-tree` or `git archive` take a `--` separator before a revision,
     * so a ref from a remote that begins with a dash would be read as an option.
     */
    protected function isUsableRef(string $ref): bool
    {
        return $ref !== '' && ! str_starts_with($ref, '-');
    }
}
