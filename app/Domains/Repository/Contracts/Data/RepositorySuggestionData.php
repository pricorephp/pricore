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
}
