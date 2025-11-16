<?php

namespace App\Domains\Repository\Actions;

use App\Domains\Repository\Contracts\Enums\GitProvider;

class ExtractRepositoryNameAction
{
    public function handle(string $repoIdentifier, GitProvider $provider): string
    {
        return match ($provider) {
            GitProvider::GitHub, GitProvider::GitLab, GitProvider::Bitbucket => $this->extractNameFromSlug($repoIdentifier),
            GitProvider::Git => $this->extractNameFromUrl($repoIdentifier),
        };
    }

    protected function extractNameFromSlug(string $slug): string
    {
        $parts = explode('/', $slug);

        return end($parts) ?: $slug;
    }

    protected function extractNameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! $path) {
            return basename($url, '.git');
        }

        $path = trim($path, '/');
        $parts = explode('/', $path);
        $name = end($parts) ?: $url;

        return basename($name, '.git');
    }
}
