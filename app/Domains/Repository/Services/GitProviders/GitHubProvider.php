<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Data\RepositorySuggestionData;
use App\Domains\Repository\Exceptions\GitProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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
            ->retry(
                times: 3,
                sleepMilliseconds: function (int $attempt, \Throwable $exception) {
                    $response = $exception instanceof RequestException ? $exception->response : null;

                    if ($response && $this->isRateLimited($response)) {
                        return $this->calculateRateLimitDelay($response);
                    }

                    // Exponential backoff: 1s, 5s, 15s
                    return (int) (1000 * pow(5, $attempt - 1));
                },
                when: function (\Throwable $exception): bool {
                    $response = $exception instanceof RequestException ? $exception->response : null;

                    if ($response && $this->isRateLimited($response)) {
                        Log::warning('GitHub API rate limit hit, retrying after delay', [
                            'repository' => $this->repositoryIdentifier,
                            'remaining' => $response->header('X-RateLimit-Remaining'),
                            'reset' => $response->header('X-RateLimit-Reset'),
                        ]);

                        return true;
                    }

                    // Retry on server errors
                    if ($response) {
                        return $response->serverError();
                    }

                    return false;
                },
                throw: true,
            )
            ->withResponseMiddleware(function (\Psr\Http\Message\ResponseInterface $response) {
                $remaining = $response->getHeaderLine('X-RateLimit-Remaining');

                if ($remaining !== '' && (int) $remaining < 100) {
                    Log::warning('GitHub API rate limit running low', [
                        'repository' => $this->repositoryIdentifier,
                        'remaining' => $remaining,
                        'reset' => $response->getHeaderLine('X-RateLimit-Reset'),
                    ]);
                }

                return $response;
            });
    }

    protected function isRateLimited(Response $response): bool
    {
        return in_array($response->status(), [403, 429])
            && $response->header('X-RateLimit-Remaining') === '0';
    }

    /**
     * Calculate delay in milliseconds until the rate limit resets (capped at 60s).
     */
    protected function calculateRateLimitDelay(Response $response): int
    {
        $resetTimestamp = (int) $response->header('X-RateLimit-Reset');
        $delaySeconds = max(0, $resetTimestamp - time());

        return min($delaySeconds, 60) * 1000;
    }

    /**
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

    public function getRepositoryUrl(): string
    {
        return "git@github.com:{$this->repositoryIdentifier}.git";
    }

    /**
     * @return array{id: int, active: bool, config: array<string, mixed>}
     */
    public function createWebhook(string $url, string $secret): array
    {
        try {
            $response = $this->http->post("/repos/{$this->repositoryIdentifier}/hooks", [
                'name' => 'web',
                'active' => true,
                'events' => ['push', 'release'],
                'config' => [
                    'url' => $url,
                    'content_type' => 'json',
                    'secret' => $secret,
                    'insecure_ssl' => '0',
                ],
            ]);

            if ($response->failed()) {
                throw new GitProviderException(
                    "Failed to create webhook at GitHub: {$response->body()}"
                );
            }

            return $response->json();
        } catch (GitProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GitHub API error creating webhook', [
                'repository' => $this->repositoryIdentifier,
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to create webhook: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function deleteWebhook(int $hookId): void
    {
        try {
            $response = $this->http->delete("/repos/{$this->repositoryIdentifier}/hooks/{$hookId}");

            if ($response->status() !== 404 && $response->failed()) {
                throw new GitProviderException(
                    "Failed to delete webhook at GitHub: {$response->body()}"
                );
            }
        } catch (GitProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GitHub API error deleting webhook', [
                'repository' => $this->repositoryIdentifier,
                'hookId' => $hookId,
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to delete webhook: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function pingWebhook(int $hookId): void
    {
        try {
            $response = $this->http->post("/repos/{$this->repositoryIdentifier}/hooks/{$hookId}/pings");

            if ($response->failed()) {
                throw new GitProviderException(
                    "Failed to ping webhook at GitHub: {$response->body()}"
                );
            }
        } catch (GitProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GitHub API error pinging webhook', [
                'repository' => $this->repositoryIdentifier,
                'hookId' => $hookId,
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to ping webhook: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * @return array<int, RepositorySuggestionData>
     */
    public function getRepositories(): array
    {
        try {
            $repositories = [];
            $page = 1;
            $perPage = 100;

            do {
                $response = $this->http->get('/user/repos', [
                    'per_page' => $perPage,
                    'page' => $page,
                    'sort' => 'updated',
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch repositories from GitHub: {$response->body()}"
                    );
                }

                $pageRepos = $response->json();

                foreach ($pageRepos as $repo) {
                    $repositories[] = RepositorySuggestionData::fromGitHubArray($repo);
                }

                $page++;
            } while (count($pageRepos) === $perPage);

            return $repositories;
        } catch (\Exception $e) {
            Log::error('GitHub API error fetching repositories', [
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to fetch repositories: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}
