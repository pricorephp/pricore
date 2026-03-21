<?php

namespace App\Domains\Repository\Contracts\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RepositorySuggestionData extends Data
{
    public function __construct(
        public string $name,
        public string $fullName,
        public bool $isPrivate,
        public ?string $description,
        public bool $isConnected = false,
    ) {}

    /**
     * Create instance from GitHub API response array.
     *
     * @param  array<string, mixed>  $githubRepo
     */
    public static function fromGitHubArray(array $githubRepo): self
    {
        return new self(
            name: $githubRepo['name'],
            fullName: $githubRepo['full_name'],
            isPrivate: $githubRepo['private'],
            description: $githubRepo['description'] ?? null,
        );
    }

    /**
     * Create instance from GitLab API response array.
     *
     * @param  array<string, mixed>  $gitlabProject
     */
    public static function fromGitLabArray(array $gitlabProject): self
    {
        return new self(
            name: $gitlabProject['name'],
            fullName: $gitlabProject['path_with_namespace'],
            isPrivate: ($gitlabProject['visibility'] ?? 'private') !== 'public',
            description: $gitlabProject['description'] ?? null,
        );
    }

    /**
     * Create instance from Bitbucket API response array.
     *
     * @param  array<string, mixed>  $bitbucketRepo
     */
    public static function fromBitbucketArray(array $bitbucketRepo): self
    {
        return new self(
            name: $bitbucketRepo['slug'],
            fullName: $bitbucketRepo['full_name'],
            isPrivate: $bitbucketRepo['is_private'],
            description: $bitbucketRepo['description'] ?? null,
        );
    }
}
