<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Data\RepositorySuggestionData;
use App\Domains\Repository\Exceptions\GitProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class GitLabProvider extends AbstractGitProvider
{
    protected function configureHttpClient(): PendingRequest
    {
        $token = $this->getCredential('token');
        $baseUrl = rtrim($this->getBaseUrl(), '/').'/api/v4';

        return Http::baseUrl($baseUrl)
            ->withToken($token ?? '')
            ->timeout(30)
            ->retry(
                times: 3,
                sleepMilliseconds: function (int $attempt, \Throwable $exception) {
                    $response = $exception instanceof RequestException ? $exception->response : null;

                    if ($response && $response->status() === 429) {
                        $retryAfter = (int) ($response->header('Retry-After') ?: '60');

                        Log::warning('GitLab API rate limit hit, retrying after delay', [
                            'repository' => $this->repositoryIdentifier,
                            'retry_after' => $retryAfter,
                        ]);

                        return min($retryAfter, 60) * 1000;
                    }

                    return (int) (1000 * pow(5, $attempt - 1));
                },
                when: function (\Throwable $exception): bool {
                    $response = $exception instanceof RequestException ? $exception->response : null;

                    if ($response && $response->status() === 429) {
                        return true;
                    }

                    if ($response) {
                        return $response->serverError();
                    }

                    return false;
                },
                throw: true,
            );
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->getCredential('url', 'https://gitlab.com'), '/');
    }

    protected function getEncodedProjectPath(): string
    {
        return urlencode($this->repositoryIdentifier);
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
            $projectPath = $this->getEncodedProjectPath();

            do {
                $response = $this->http->get("/projects/{$projectPath}/repository/tags", [
                    'per_page' => $perPage,
                    'page' => $page,
                ]);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch tags from GitLab: {$response->body()}"
                    );
                }

                $pageTags = $response->json();

                foreach ($pageTags as $tag) {
                    $tags[] = [
                        'name' => $tag['name'],
                        'commit' => $tag['commit']['id'],
                    ];
                }

                $page++;
            } while (count($pageTags) === $perPage);

            return $tags;
        } catch (\Exception $e) {
            Log::error('GitLab API error fetching tags', [
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
            $projectPath = $this->getEncodedProjectPath();

            do {
                $response = $this->http->get("/projects/{$projectPath}/repository/branches", [
                    'per_page' => $perPage,
                    'page' => $page,
                ]);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch branches from GitLab: {$response->body()}"
                    );
                }

                $pageBranches = $response->json();

                foreach ($pageBranches as $branch) {
                    $branches[] = [
                        'name' => $branch['name'],
                        'commit' => $branch['commit']['id'],
                    ];
                }

                $page++;
            } while (count($pageBranches) === $perPage);

            return $branches;
        } catch (\Exception $e) {
            Log::error('GitLab API error fetching branches', [
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
            $projectPath = $this->getEncodedProjectPath();
            $encodedPath = urlencode($path);

            $response = $this->http->get("/projects/{$projectPath}/repository/files/{$encodedPath}", [
                'ref' => $ref,
            ]);

            if ($response->status() === 404) {
                return null;
            }

            if ($response->failed()) {
                throw new GitProviderException(
                    "Failed to fetch file from GitLab: {$response->body()}"
                );
            }

            $data = $response->json();

            if (isset($data['content']) && ($data['encoding'] ?? '') === 'base64') {
                return base64_decode($data['content']);
            }

            return $data['content'] ?? null;
        } catch (\Exception $e) {
            Log::error('GitLab API error fetching file content', [
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
            $projectPath = $this->getEncodedProjectPath();
            $response = $this->http->get("/projects/{$projectPath}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('GitLab API error validating credentials', [
                'repository' => $this->repositoryIdentifier,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getRepositoryUrl(): string
    {
        $baseUrl = $this->getBaseUrl();

        return "{$baseUrl}/{$this->repositoryIdentifier}.git";
    }

    public function downloadArchive(string $ref, string $outputPath): bool
    {
        try {
            $projectPath = $this->getEncodedProjectPath();

            /** @var Response $response */
            $response = $this->http
                ->withOptions(['sink' => $outputPath])
                ->get("/projects/{$projectPath}/repository/archive.zip", [
                    'sha' => $ref,
                ]);

            return $response->successful() && file_exists($outputPath);
        } catch (\Exception $e) {
            Log::warning('GitLab API error downloading archive', [
                'repository' => $this->repositoryIdentifier,
                'ref' => $ref,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return array{id: int, active: bool}
     */
    public function createWebhook(string $url, string $secret): array
    {
        try {
            $projectPath = $this->getEncodedProjectPath();

            $response = $this->http->post("/projects/{$projectPath}/hooks", [
                'url' => $url,
                'push_events' => true,
                'tag_push_events' => true,
                'token' => $secret,
                'enable_ssl_verification' => true,
            ]);

            if ($response->failed()) {
                throw new GitProviderException(
                    "Failed to create webhook at GitLab: {$response->body()}"
                );
            }

            $data = $response->json();

            return [
                'id' => $data['id'],
                'active' => true,
            ];
        } catch (GitProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GitLab API error creating webhook', [
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
            $projectPath = $this->getEncodedProjectPath();

            $response = $this->http->delete("/projects/{$projectPath}/hooks/{$hookId}");

            if ($response->status() !== 404 && $response->failed()) {
                throw new GitProviderException(
                    "Failed to delete webhook at GitLab: {$response->body()}"
                );
            }
        } catch (GitProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GitLab API error deleting webhook', [
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
                $response = $this->http->get('/projects', [
                    'membership' => true,
                    'min_access_level' => 20,
                    'per_page' => $perPage,
                    'page' => $page,
                    'order_by' => 'updated_at',
                    'sort' => 'desc',
                ]);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch repositories from GitLab: {$response->body()}"
                    );
                }

                $pageRepos = $response->json();

                foreach ($pageRepos as $repo) {
                    $repositories[] = RepositorySuggestionData::fromGitLabArray($repo);
                }

                $page++;
            } while (count($pageRepos) === $perPage);

            return $repositories;
        } catch (\Exception $e) {
            Log::error('GitLab API error fetching repositories', [
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to fetch repositories: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}
