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
            $this->updateClone($repository, $clonePath);

            return $clonePath;
        }

        $this->createClone($repository, $clonePath);

        return $clonePath;
    }

    protected function createClone(Repository $repository, string $clonePath): void
    {
        $env = $this->buildGitEnvironment($repository);
        $tempKeyFile = $env['_temp_key_file'] ?? null;
        unset($env['_temp_key_file']);

        try {
            $result = Process::env($env)
                ->run(['git', 'clone', '--bare', $repository->repo_identifier, $clonePath]);

            if ($result->failed()) {
                throw new GitProviderException('Failed to clone repository: '.$result->errorOutput());
            }
        } finally {
            if ($tempKeyFile !== null && file_exists($tempKeyFile)) {
                unlink($tempKeyFile);
            }
        }
    }

    protected function updateClone(Repository $repository, string $clonePath): void
    {
        $env = $this->buildGitEnvironment($repository);
        $tempKeyFile = $env['_temp_key_file'] ?? null;
        unset($env['_temp_key_file']);

        try {
            $result = Process::path($clonePath)
                ->env($env)
                ->run(['git', 'fetch', '--all', '--prune']);

            if ($result->failed()) {
                throw new GitProviderException('Failed to update repository clone: '.$result->errorOutput());
            }
        } finally {
            if ($tempKeyFile !== null && file_exists($tempKeyFile)) {
                unlink($tempKeyFile);
            }
        }
    }

    /**
     * @return array<string, string|null>
     */
    protected function buildGitEnvironment(Repository $repository): array
    {
        $env = [
            'GIT_TERMINAL_PROMPT' => '0',
        ];

        $sshKey = $repository->sshKey;

        if ($sshKey) {
            $tempKeyFile = sys_get_temp_dir().'/pricore-ssh-'.Str::random(16);
            file_put_contents($tempKeyFile, $sshKey->private_key."\n");
            chmod($tempKeyFile, 0600);
            $env['GIT_SSH_COMMAND'] = "ssh -i {$tempKeyFile} -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes";
            $env['_temp_key_file'] = $tempKeyFile;
        }

        return $env;
    }
}
