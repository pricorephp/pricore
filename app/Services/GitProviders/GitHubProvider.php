<?php

namespace App\Services\GitProviders;

use App\Domains\Repository\Exceptions\GitProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubProvider extends AbstractGitProvider
{
    protected function configureHttpClient(): PendingRequest
    {
        $token = $this->getCredential('token');

        return Http::baseUrl('https://api.github.com')
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->when($token, fn (PendingRequest $http) => $http->withToken($token))
            ->timeout(30)
            ->retry(3, 1000);
    }

    /**
     * Get all tags from the repository.
     *
     * @return array<int, array{name: string, commit: string}>
     */
    public function getTags(): array
    {
        try {
            $tags = [];
            $page = 1;
            $perPage = 100;

            do {
                $response = $this->http->get("/repos/{$this->repositoryIdentifier}/tags", [
                    'per_page' => $perPage,
                    'page' => $page,
                ]);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch tags from GitHub: {$response->body()}"
                    );
                }

                $pageTags = $response->json();

                foreach ($pageTags as $tag) {
                    $tags[] = [
                        'name' => $tag['name'],
                        'commit' => $tag['commit']['sha'],
                    ];
                }

                $page++;
            } while (count($pageTags) === $perPage);

            return $tags;
        } catch (\Exception $e) {
            Log::error('GitHub API error fetching tags', [
                'repository' => $this->repositoryIdentifier,
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to fetch tags: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Get all branches from the repository.
     *
     * @return array<int, array{name: string, commit: string}>
     */
    public function getBranches(): array
    {
        try {
            $branches = [];
            $page = 1;
            $perPage = 100;

            do {
                $response = $this->http->get("/repos/{$this->repositoryIdentifier}/branches", [
                    'per_page' => $perPage,
                    'page' => $page,
                ]);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch branches from GitHub: {$response->body()}"
                    );
                }

                $pageBranches = $response->json();

                foreach ($pageBranches as $branch) {
                    $branches[] = [
                        'name' => $branch['name'],
                        'commit' => $branch['commit']['sha'],
                    ];
                }

                $page++;
            } while (count($pageBranches) === $perPage);

            return $branches;
        } catch (\Exception $e) {
            Log::error('GitHub API error fetching branches', [
                'repository' => $this->repositoryIdentifier,
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to fetch branches: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Get file content from a specific reference.
     */
    public function getFileContent(string $ref, string $path): ?string
    {
        try {
            $response = $this->http->get("/repos/{$this->repositoryIdentifier}/contents/{$path}", [
                'ref' => $ref,
            ]);

            if ($response->status() === 404) {
                return null;
            }

            if ($response->failed()) {
                throw new GitProviderException(
                    "Failed to fetch file from GitHub: {$response->body()}"
                );
            }

            $data = $response->json();

            // GitHub returns file content as base64
            if (isset($data['content']) && $data['type'] === 'file') {
                return base64_decode(str_replace("\n", '', $data['content']));
            }

            return null;
        } catch (\Exception $e) {
            Log::error('GitHub API error fetching file content', [
                'repository' => $this->repositoryIdentifier,
                'ref' => $ref,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to fetch file content: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Validate that the credentials are valid and can access the repository.
     */
    public function validateCredentials(): bool
    {
        try {
            $response = $this->http->get("/repos/{$this->repositoryIdentifier}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('GitHub API error validating credentials', [
                'repository' => $this->repositoryIdentifier,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the Git repository URL for cloning.
     */
    public function getRepositoryUrl(): string
    {
        return "git@github.com:{$this->repositoryIdentifier}.git";
    }
}
