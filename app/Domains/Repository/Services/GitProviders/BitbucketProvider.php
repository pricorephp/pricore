<?php

namespace App\Domains\Repository\Services\GitProviders;

use App\Domains\Repository\Contracts\Data\RepositorySuggestionData;
use App\Domains\Repository\Exceptions\GitProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BitbucketProvider extends AbstractGitProvider
{
    protected function configureHttpClient(): PendingRequest
    {
        $email = $this->getCredential('email', '');
        $apiToken = $this->getCredential('api_token', '');

        return Http::baseUrl('https://api.bitbucket.org/2.0')
            ->withBasicAuth($email, $apiToken)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->retry(
                times: 3,
                sleepMilliseconds: function (int $attempt, \Throwable $exception) {
                    $response = $exception instanceof RequestException ? $exception->response : null;

                    if ($response && $response->status() === 429) {
                        $retryAfter = (int) ($response->header('Retry-After') ?: '60');

                        Log::warning('Bitbucket API rate limit hit, retrying after delay', [
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
                throw: false,
            );
    }

    /**
     * @return array<int, array{name: string, commit: string}>
     */
    public function getTags(): array
    {
        try {
            $tags = [];
            $url = "/repositories/{$this->repositoryIdentifier}/refs/tags";
            $params = ['pagelen' => 100];

            do {
                $response = $params === []
                    ? $this->http->get($url)
                    : $this->http->get($url, $params);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch tags from Bitbucket: {$response->body()}"
                    );
                }

                $data = $response->json();

                foreach ($data['values'] ?? [] as $tag) {
                    $tags[] = [
                        'name' => $tag['name'],
                        'commit' => $tag['target']['hash'],
                    ];
                }

                $url = $this->extractNextPath($data['next'] ?? null);
                $params = [];
            } while ($url !== null);

            return $tags;
        } catch (\Exception $e) {
            Log::error('Bitbucket API error fetching tags', [
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
            $url = "/repositories/{$this->repositoryIdentifier}/refs/branches";
            $params = ['pagelen' => 100];

            do {
                $response = $params === []
                    ? $this->http->get($url)
                    : $this->http->get($url, $params);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch branches from Bitbucket: {$response->body()}"
                    );
                }

                $data = $response->json();

                foreach ($data['values'] ?? [] as $branch) {
                    $branches[] = [
                        'name' => $branch['name'],
                        'commit' => $branch['target']['hash'],
                    ];
                }

                $url = $this->extractNextPath($data['next'] ?? null);
                $params = [];
            } while ($url !== null);

            return $branches;
        } catch (\Exception $e) {
            Log::error('Bitbucket API error fetching branches', [
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
            $response = $this->http
                ->accept('application/octet-stream')
                ->get("/repositories/{$this->repositoryIdentifier}/src/{$ref}/{$path}");

            if ($response->status() === 404) {
                return null;
            }

            if ($response->failed()) {
                throw new GitProviderException(
                    "Failed to fetch file from Bitbucket: {$response->body()}"
                );
            }

            return $response->body();
        } catch (\Exception $e) {
            Log::error('Bitbucket API error fetching file content', [
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
            $response = $this->http->get("/repositories/{$this->repositoryIdentifier}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Bitbucket API error validating credentials', [
                'repository' => $this->repositoryIdentifier,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getRepositoryUrl(): string
    {
        return "git@bitbucket.org:{$this->repositoryIdentifier}.git";
    }

    /**
     * @return array{id: string, active: bool}
     */
    public function createWebhook(string $url, string $secret): array
    {
        try {
            $response = $this->http->post("/repositories/{$this->repositoryIdentifier}/hooks", [
                'description' => 'Pricore',
                'url' => $url,
                'active' => true,
                'events' => ['repo:push'],
                'secret' => $secret,
            ]);

            if ($response->failed()) {
                throw new GitProviderException(
                    "Failed to create webhook at Bitbucket: {$response->body()}"
                );
            }

            $data = $response->json();

            return [
                'id' => $data['uuid'],
                'active' => $data['active'] ?? true,
            ];
        } catch (GitProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Bitbucket API error creating webhook', [
                'repository' => $this->repositoryIdentifier,
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to create webhook: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function deleteWebhook(int|string $hookId): void
    {
        try {
            $response = $this->http->delete("/repositories/{$this->repositoryIdentifier}/hooks/{$hookId}");

            if ($response->status() !== 404 && $response->failed()) {
                throw new GitProviderException(
                    "Failed to delete webhook at Bitbucket: {$response->body()}"
                );
            }
        } catch (GitProviderException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Bitbucket API error deleting webhook', [
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

    public function downloadArchive(string $ref, string $outputPath): bool
    {
        try {
            $email = $this->getCredential('email', '');
            $apiToken = $this->getCredential('api_token', '');

            $response = Http::withBasicAuth($email, $apiToken)
                ->timeout(60)
                ->withOptions(['sink' => $outputPath])
                ->get("https://bitbucket.org/{$this->repositoryIdentifier}/get/{$ref}.zip");

            return $response->successful() && file_exists($outputPath);
        } catch (\Exception $e) {
            Log::warning('Bitbucket API error downloading archive', [
                'repository' => $this->repositoryIdentifier,
                'ref' => $ref,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Bitbucket Cloud sunset its cross-workspace enumeration APIs in
     * CHANGE-2770 (2026-04-14): /workspaces, /repositories?role=member, and
     * related endpoints all return a deprecation error. There is no
     * replacement for listing the workspaces an authenticated user belongs to,
     * so the UI must collect the workspace slug from the user instead.
     *
     * @return array<int, string>
     */
    public function getOwners(): array
    {
        return [];
    }

    /**
     * @return array<int, RepositorySuggestionData>
     */
    public function getRepositories(?string $owner = null): array
    {
        if ($owner === null || $owner === '') {
            throw new GitProviderException(
                'Bitbucket requires a workspace to list repositories. Cross-workspace listing was sunset by Atlassian on 2026-04-14.'
            );
        }

        try {
            $repositories = [];
            $url = "/repositories/{$owner}";
            $params = ['pagelen' => 100];

            do {
                $response = $params === []
                    ? $this->http->get($url)
                    : $this->http->get($url, $params);
                $this->throwIfResponseUnauthorized($response);

                if ($response->failed()) {
                    throw new GitProviderException(
                        "Failed to fetch repositories from Bitbucket: {$response->body()}"
                    );
                }

                $data = $response->json();

                foreach ($data['values'] ?? [] as $repo) {
                    $repositories[] = RepositorySuggestionData::fromBitbucketArray($repo);
                }

                $url = $this->extractNextPath($data['next'] ?? null);
                $params = [];
            } while ($url !== null);

            return $repositories;
        } catch (\Exception $e) {
            Log::error('Bitbucket API error fetching repositories', [
                'error' => $e->getMessage(),
            ]);

            throw new GitProviderException(
                "Failed to fetch repositories: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Throw a user-friendly error if the response indicates auth/scope failure.
     */
    protected function throwIfResponseUnauthorized(Response $response): void
    {
        if ($response->status() === 401) {
            throw new GitProviderException(
                'Invalid Bitbucket credentials. Please check your email and API token in Settings → Git Providers.'
            );
        }

        if ($response->status() === 403) {
            $detail = $response->json('error.detail.required', []);
            $scopes = $detail ? ' Required scopes: '.implode(', ', $detail).'.' : '';

            throw new GitProviderException(
                'Your Bitbucket API token is missing required scopes.'.$scopes
            );
        }
    }

    /**
     * Extract the relative path from a Bitbucket absolute next URL.
     */
    protected function extractNextPath(?string $nextUrl): ?string
    {
        if ($nextUrl === null) {
            return null;
        }

        $parsed = parse_url($nextUrl);
        if ($parsed === false || ! isset($parsed['path'])) {
            return null;
        }

        $path = $parsed['path'];

        // Remove the /2.0 prefix since the HTTP client already has it as base URL
        if (str_starts_with($path, '/2.0')) {
            $path = substr($path, 4);
        }

        if (isset($parsed['query'])) {
            $path .= '?'.$parsed['query'];
        }

        return $path;
    }
}
