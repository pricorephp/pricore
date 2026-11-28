<?php

namespace App\Domains\Repository\Rules;

use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use App\Domains\Repository\Services\Http\GitLabUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeGitLabUrl implements ValidationRule
{
    public function __construct(
        private readonly GitLabUrlGuard $gitLabUrlGuard,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The GitLab URL must be a valid URL.');

            return;
        }

        try {
            $this->gitLabUrlGuard->resolve($value);
        } catch (UnsafeMirrorUrlException) {
            $fail('The GitLab URL must be a public HTTP or HTTPS address without embedded credentials. Private hosts have to be allowed by an administrator.');
        }
    }
}
