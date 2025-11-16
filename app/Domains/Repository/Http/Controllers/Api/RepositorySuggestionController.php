<?php

namespace App\Domains\Repository\Http\Controllers\Api;

use App\Domains\Repository\Contracts\Data\RepositorySuggestionData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationGitCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepositorySuggestionController extends Controller
{
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $provider = GitProvider::tryFrom($request->query('provider'));

        if (! $provider) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        $credential = OrganizationGitCredential::query()
            ->where('organization_uuid', $organization->uuid)
            ->where('provider', $provider)
            ->first();

        if (! $credential) {
            return response()->json(['error' => 'No credentials found for this provider'], 404);
        }

        try {
            $repositories = match ($provider) {
                GitProvider::GitHub => $this->getGitHubRepositories($credential->credentials),
                GitProvider::GitLab, GitProvider::Bitbucket, GitProvider::Git => throw new \RuntimeException(
                    "Provider '{$provider->label()}' repository suggestions are not yet implemented"
                ),
            };

            $suggestions = array_map(
                fn (array $repo) => RepositorySuggestionData::from([
                    'name' => $repo['name'],
                    'fullName' => $repo['full_name'],
                    'isPrivate' => $repo['private'],
                    'description' => $repo['description'],
                ]),
                $repositories
            );

            return response()->json(['repositories' => $suggestions]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get GitHub repositories using credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<int, array{name: string, full_name: string, private: bool, description: string|null}>
     */
    protected function getGitHubRepositories(array $credentials): array
    {
        $provider = new GitHubProvider('', $credentials);

        return $provider->getRepositories();
    }
}
