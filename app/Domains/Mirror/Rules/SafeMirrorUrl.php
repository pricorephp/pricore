<?php

namespace App\Domains\Mirror\Rules;

use App\Domains\Mirror\Exceptions\UnsafeMirrorUrlException;
use App\Domains\Mirror\Services\Http\MirrorUrlPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeMirrorUrl implements ValidationRule
{
    public function __construct(
        private readonly MirrorUrlPolicy $urlPolicy,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        try {
            $this->urlPolicy->resolveMirrorOrigin($value);
        } catch (UnsafeMirrorUrlException $exception) {
            $fail($exception->getMessage());
        }
    }
}
