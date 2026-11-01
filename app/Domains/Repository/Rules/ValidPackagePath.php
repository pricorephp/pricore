<?php

namespace App\Domains\Repository\Rules;

use App\Domains\Repository\Services\PackagePaths\PackagePathPattern;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPackagePath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PackagePathPattern::isValid($value)) {
            $fail(sprintf(
                '"%s" is not a valid package path. Use a directory such as "packages/billing", a wildcard such as "packages/*", or "." for the repository root.',
                is_scalar($value) ? $value : '',
            ));
        }
    }
}
