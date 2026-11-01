<?php

namespace App\Domains\Token\Requests;

use App\Domains\Token\Contracts\Enums\TokenScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessTokenRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'string'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => [Rule::enum(TokenScope::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Convert "never" to null for expiration
        if ($this->expires_at === 'never') {
            $this->merge(['expires_at' => null]);
        }
    }
}
