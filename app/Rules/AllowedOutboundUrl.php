<?php

namespace App\Rules;

use App\Services\Http\OutboundUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedOutboundUrl implements ValidationRule
{
    public function __construct(private readonly OutboundUrlGuard $guard) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if ($this->guard->allows($value)) {
            return;
        }

        $fail('The :attribute must point at a publicly reachable host. Add the hostname to OUTBOUND_ALLOWED_HOSTS to allow an internal one.');
    }
}
