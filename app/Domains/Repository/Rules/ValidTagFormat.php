<?php

namespace App\Domains\Repository\Rules;

use App\Domains\Repository\Services\TagFormats\TagFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTagFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! TagFormat::isValid($value)) {
            $fail('The tag format must contain {version} and one of {name}, {package} or {path}, with at least one character between them, such as "{package}@{version}".');
        }
    }
}
