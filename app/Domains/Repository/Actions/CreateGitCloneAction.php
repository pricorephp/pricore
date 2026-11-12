<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Exceptions\GitProviderException;
use App\Domains\Repository\Services\GitProviders\GenericGitProvider;
use App\Domains\Repository\Services\GitRemotes\GitRemotePolicy;
use App\Domains\Repository\Services\GitRemotes\ResolvedGitRemote;
use App\Models\Repository;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class CreateGitCloneAction
{
    public function __construct(
        protected GitRemotePolicy $gitRemotePolicy,
    ) {}

    public function handle(Repository $repository): ?string
    {
        if ($repository->provider !== GitProvider::Git) {
            return null;
        }

        // Checked at the point of use, not only at request validation: the row may
        // predate that validation, and the host may have moved since it was added.
        $gitRemote = $this->gitRemotePolicy->resolve($repository->repo_identifier);

        $clonePath = storage_path("app/git-clones/{$repository->uuid}");

        if (is_dir($clonePath)) {
            $this->updateClone($repository, $gitRemote, $clonePath);

            return $clonePath;
        }

        $this->createClone($repository, $gitRemote, $clonePath);

        return $clonePath;
    }

    protected function createClone(Repository $repository, ResolvedGitRemote $gitRemote, string $clonePath): void
    {
        $env = $this->buildGitEnvironment($repository, $gitRemote);
        $tempKeyFile = $env['_temp_key_file'] ?? null;
        unset($env['_temp_key_file']);

        try {
            $result = Process::env($env)
                ->run(['git', ...$gitRemote->gitOptions(), 'clone', '--bare', '--', $gitRemote->url, $clonePath]);

            if ($result->failed()) {
                throw new GitProviderException('Failed to clone repository: '.GenericGitProvider::redactUrlCredentials($result->errorOutput()));
            }
        } finally {
            if ($tempKeyFile !== null && file_exists($tempKeyFile)) {
                unlink($tempKeyFile);
            }
        }
    }

    protected function updateClone(Repository $repository, ResolvedGitRemote $gitRemote, string $clonePath): void
    {
        $env = $this->buildGitEnvironment($repository, $gitRemote);
        $tempKeyFile = $env['_temp_key_file'] ?? null;
        unset($env['_temp_key_file']);

        try {
            // A bare clone has no fetch refspec, so the refs to update must be named
            // explicitly; otherwise the fetch only writes FETCH_HEAD. It fetches from the
            // repository's address because a leftover clone's origin can be out of date.
            $result = Process::path($clonePath)
                ->env($env)
                ->run(['git', ...$gitRemote->gitOptions(), 'fetch', '--prune', '--', $gitRemote->url, '+refs/heads/*:refs/heads/*', '+refs/tags/*:refs/tags/*']);

            if ($result->failed()) {
                throw new GitProviderException('Failed to update repository clone: '.GenericGitProvider::redactUrlCredentials($result->errorOutput()));
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
    protected function buildGitEnvironment(Repository $repository, ResolvedGitRemote $gitRemote): array
    {
        $env = [
            'GIT_TERMINAL_PROMPT' => '0',
            ...$gitRemote->environment(),
        ];

        $sshKey = $repository->sshKey;

        if ($sshKey) {
            if ($sshKey->organization_uuid !== $repository->organization_uuid) {
                throw new GitProviderException('The SSH key does not belong to the repository organization.');
            }

            $tempKeyFile = sys_get_temp_dir().'/pricore-ssh-'.Str::random(16);
            file_put_contents($tempKeyFile, $sshKey->private_key."\n");
            chmod($tempKeyFile, 0600);
            $env['GIT_SSH_COMMAND'] = "ssh -i {$tempKeyFile} -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=yes".$gitRemote->sshOptions();
            $env['_temp_key_file'] = $tempKeyFile;
        } elseif ($gitRemote->sshOptions() !== '') {
            $env['GIT_SSH_COMMAND'] = (getenv('GIT_SSH_COMMAND') ?: 'ssh').$gitRemote->sshOptions();
        }

        return $env;
    }
}
