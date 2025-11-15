<?php

namespace App\Console\Commands;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\Organization;
use App\Models\OrganizationGitCredential;
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
                            {organization? : The UUID or slug of the organization}
                            {--provider= : The Git provider (github, gitlab, bitbucket, git)}
                            {--token= : The authentication token}';

    /**
     * @var string
     */
    protected $description = 'Add Git credentials to an organization';

    public function handle(): int
    {
        $organization = $this->organization();

        if (! $organization) {
            $this->error('Organization not found.');

            return self::FAILURE;
        }

        $provider = $this->provider();
        $credentials = $this->credentials($provider);

        // Check if credentials already exist for this provider
        $existingCredential = OrganizationGitCredential::query()
            ->where('organization_uuid', $organization->uuid)
            ->where('provider', $provider->value)
            ->first();

        if ($existingCredential) {
            if (! $this->confirm("Credentials for {$provider->label()} already exist. Do you want to update them?")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }

            $existingCredential->update(['credentials' => $credentials]);
            $this->info("Credentials for {$provider->label()} have been updated successfully.");
        } else {
            OrganizationGitCredential::create([
                'organization_uuid' => $organization->uuid,
                'provider' => $provider->value,
                'credentials' => $credentials,
            ]);

            $this->info("Credentials for {$provider->label()} have been added successfully.");
        }

        return self::SUCCESS;
    }

    protected function organization(): ?Organization
    {
        if ($organizationIdentifier = $this->argument('organization')) {
            return Organization::query()
                ->where('uuid', $organizationIdentifier)
                ->orWhere('slug', $organizationIdentifier)
                ->first();
        }

        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->error('No organizations found. Please create an organization first.');

            return null;
        }

        $selectedUuid = search(
            label: 'Select an organization',
            options: fn (string $value) => $value !== ''
                ? $organizations
                    ->filter(fn ($org) => str_contains(strtolower($org->name), strtolower($value))
                        || str_contains(strtolower($org->slug), strtolower($value)))
                    ->mapWithKeys(fn ($org) => [$org->uuid => "{$org->name} ({$org->slug})"])
                    ->all()
                : $organizations
                    ->mapWithKeys(fn ($org) => [$org->uuid => "{$org->name} ({$org->slug})"])
                    ->all(),
            placeholder: 'Search organizations...'
        );

        return Organization::find($selectedUuid);
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
     * Get credentials based on provider.
     *
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
     * Get GitHub credentials.
     *
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
     * Get GitLab credentials.
     *
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
     * Get Bitbucket credentials.
     *
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
     * Get generic Git credentials.
     *
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
