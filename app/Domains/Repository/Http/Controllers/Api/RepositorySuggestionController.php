<?php

namespace App\Domains\Repository\Http\Controllers\Api;

use App\Domains\Repository\Actions\ResolveUserGitCredentialAction;
use App\Domains\Repository\Contracts\Data\RepositorySuggestionData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Services\GitProviders\BitbucketProvider;
use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use App\Domains\Repository\Services\GitProviders\GitLabProvider;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RepositorySuggestionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Organization $organization, ResolveUserGitCredentialAction $resolveUserGitCredentialAction): JsonResponse
    {
        $this->authorize('viewSettings', $organization);

        $provider = GitProvider::tryFrom($request->query('provider'));

        if (! $provider) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        /** @var User $user */
        $user = $request->user();

        $credential = $resolveUserGitCredentialAction->handle($user, $provider, $this->credentialUuid($request));

        if (! $credential) {
            return response()->json(['error' => 'No credentials found for this provider'], 404);
        }

        $owner = $request->query('owner');
        $owner = is_string($owner) && $owner !== '' ? $owner : null;

        try {
            $repositories = match ($provider) {
                GitProvider::GitHub => $this->getGitHubRepositories($credential->credentials, $owner),
                GitProvider::GitLab => $this->getGitLabRepositories($credential->credentials, $owner),
                GitProvider::Bitbucket => $this->getBitbucketRepositories($credential->credentials, $owner),
                GitProvider::Git => throw new \RuntimeException(
                    "Provider '{$provider->label()}' repository suggestions are not yet implemented"
                ),
            };

            $connectedIdentifiers = Repository::query()
                ->where('organization_uuid', $organization->uuid)
                ->where('provider', $provider)
                ->when($provider->supportsSelfHosted(), fn ($query) => $query->where('custom_base_url', $credential->baseUrl()))
                ->pluck('repo_identifier')
                ->toArray();

            $repositories = array_map(
                fn (RepositorySuggestionData $repo) => new RepositorySuggestionData(
                    name: $repo->name,
                    fullName: $repo->fullName,
                    isPrivate: $repo->isPrivate,
                    description: $repo->description,
                    isConnected: in_array($repo->fullName, $connectedIdentifiers),
                ),
                $repositories,
            );

            return response()->json(['repositories' => $repositories]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function owners(Request $request, Organization $organization, ResolveUserGitCredentialAction $resolveUserGitCredentialAction): JsonResponse
    {
        $this->authorize('viewSettings', $organization);

        $provider = GitProvider::tryFrom($request->query('provider'));

        if (! $provider) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        /** @var User $user */
        $user = $request->user();

        $credential = $resolveUserGitCredentialAction->handle($user, $provider, $this->credentialUuid($request));

        if (! $credential) {
            return response()->json(['error' => 'No credentials found for this provider'], 404);
        }

        try {
            $owners = match ($provider) {
                GitProvider::GitHub => (new GitHubProvider('', $credential->credentials))->getOwners(),
                GitProvider::GitLab => (new GitLabProvider('', $credential->credentials))->getOwners(),
                GitProvider::Bitbucket => (new BitbucketProvider('', $credential->credentials))->getOwners(),
                GitProvider::Git => [],
            };

            return response()->json(['owners' => $owners]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function credentialUuid(Request $request): ?string
    {
        $credentialUuid = $request->query('credential');

        return is_string($credentialUuid) && Str::isUuid($credentialUuid) ? $credentialUuid : null;
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<int, RepositorySuggestionData>
     */
    protected function getGitHubRepositories(array $credentials, ?string $owner): array
    {
        $provider = new GitHubProvider('', $credentials);

        return $provider->getRepositories($owner);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<int, RepositorySuggestionData>
     */
    protected function getGitLabRepositories(array $credentials, ?string $owner): array
    {
        $provider = new GitLabProvider('', $credentials);

        return $provider->getRepositories($owner);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<int, RepositorySuggestionData>
     */
    protected function getBitbucketRepositories(array $credentials, ?string $owner): array
    {
        $bitbucketProvider = new BitbucketProvider('', $credentials);

        return $bitbucketProvider->getRepositories($owner);
    }
}
