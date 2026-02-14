<?php

namespace App\Console\Commands;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\User;
use App\Models\UserGitCredential;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class AddGitCredentialsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'credentials:add
                            {user? : The UUID or email of the user}
                            {--provider= : The Git provider (github, gitlab, bitbucket, git)}
                            {--token= : The authentication token}';

    /**
     * @var string
     */
    protected $description = 'Add Git credentials to a user';

    public function handle(): int
    {
        $user = $this->user();

        if (! $user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $provider = $this->provider();
        $credentials = $this->credentials($provider);

        $existingCredential = UserGitCredential::query()
            ->where('user_uuid', $user->uuid)
            ->where('provider', $provider)
            ->first();

        if ($existingCredential) {
            if (! $this->confirm("Credentials for {$provider->label()} already exist. Do you want to update them?")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $existingCredential->update(['credentials' => $credentials]);
            $this->info("Credentials for {$provider->label()} have been updated successfully.");
        } else {
            UserGitCredential::create([
                'user_uuid' => $user->uuid,
                'provider' => $provider,
                'credentials' => $credentials,
            ]);

            $this->info("Credentials for {$provider->label()} have been added successfully.");
        }

        return self::SUCCESS;
    }

    protected function user(): ?User
    {
        if ($userIdentifier = $this->argument('user')) {
            return User::query()
                ->where('uuid', $userIdentifier)
                ->orWhere('email', $userIdentifier)
                ->first();
        }

        $users = User::all();

        if ($users->isEmpty()) {
            $this->error('No users found. Please create a user first.');

            return null;
        }

        $selectedUuid = search(
            label: 'Select a user',
            options: fn (string $value) => $value !== ''
                ? $users
                    ->filter(fn ($user) => str_contains(strtolower($user->name), strtolower($value))
                        || str_contains(strtolower($user->email), strtolower($value)))
                    ->mapWithKeys(fn ($user) => [$user->uuid => "{$user->name} ({$user->email})"])
                    ->all()
                : $users
                    ->mapWithKeys(fn ($user) => [$user->uuid => "{$user->name} ({$user->email})"])
                    ->all(),
            placeholder: 'Search users...'
        );

        return User::find($selectedUuid);
    }

    protected function provider(): GitProvider
    {
        if ($provider = $this->option('provider')) {
            $providerEnum = GitProvider::tryFrom($provider);

            if (! $providerEnum) {
                $this->error("Invalid provider: {$provider}");
                $this->info('Valid providers: '.implode(', ', array_column(GitProvider::cases(), 'value')));
                exit(1);
            }

            return $providerEnum;
        }

        $selected = select(
            label: 'Select Git provider',
            options: GitProvider::options(),
            default: GitProvider::GitHub->value
        );

        return GitProvider::from($selected);
    }

    /**
     * @return array<string, string|null>
     */
    protected function credentials(GitProvider $provider): array
    {
        return match ($provider) {
            GitProvider::GitHub => $this->getGitHubCredentials(),
            GitProvider::GitLab => $this->getGitLabCredentials(),
            GitProvider::Bitbucket => $this->getBitbucketCredentials(),
            GitProvider::Git => $this->getGenericGitCredentials(),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function getGitHubCredentials(): array
    {
        if ($token = $this->option('token')) {
            return ['token' => $token];
        }

        $this->info('GitHub requires a Personal Access Token with "repo" scope.');
        $this->info('Generate one at: https://github.com/settings/tokens');

        $token = password(
            label: 'GitHub Personal Access Token',
            required: true,
            hint: 'Token will be encrypted before storage'
        );

        return ['token' => $token];
    }

    /**
     * @return array<string, string|null>
     */
    protected function getGitLabCredentials(): array
    {
        if ($token = $this->option('token')) {
            return [
                'token' => $token,
                'url' => $this->ask('GitLab URL (leave empty for gitlab.com)') ?: null,
            ];
        }

        $this->info('GitLab requires a Personal Access Token or Project Access Token with "read_api" scope.');

        $token = password(
            label: 'GitLab Access Token',
            required: true,
            hint: 'Token will be encrypted before storage'
        );

        $url = text(
            label: 'GitLab URL (optional)',
            placeholder: 'https://gitlab.example.com',
            hint: 'Leave empty for gitlab.com'
        );

        return [
            'token' => $token,
            'url' => $url ?: null,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getBitbucketCredentials(): array
    {
        $this->info('Bitbucket requires an App Password with "repository:read" permission.');

        $username = text(
            label: 'Bitbucket Username',
            required: true
        );

        $appPassword = password(
            label: 'Bitbucket App Password',
            required: true,
            hint: 'Password will be encrypted before storage'
        );

        return [
            'username' => $username,
            'app_password' => $appPassword,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getGenericGitCredentials(): array
    {
        $this->info('Generic Git repositories require an SSH key for authentication.');

        $sshKey = password(
            label: 'SSH Private Key',
            required: true,
            hint: 'Key will be encrypted before storage'
        );

        return ['ssh_key' => $sshKey];
    }
}
