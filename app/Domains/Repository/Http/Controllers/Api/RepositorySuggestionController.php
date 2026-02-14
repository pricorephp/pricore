<?php

namespace App\Domains\Repository\Http\Controllers\Api;

use App\Domains\Repository\Contracts\Data\RepositorySuggestionData;
use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Services\GitProviders\GitHubProvider;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\UserGitCredential;
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

        /** @var \App\Models\User $user */
        $user = $request->user();

        $credential = UserGitCredential::query()
            ->where('user_uuid', $user->uuid)
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

            return response()->json(['repositories' => $repositories]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<int, RepositorySuggestionData>
     */
    protected function getGitHubRepositories(array $credentials): array
    {
        $provider = new GitHubProvider('', $credentials);

        return $provider->getRepositories();
    }
}
