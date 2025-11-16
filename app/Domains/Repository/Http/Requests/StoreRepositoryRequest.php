<?php

namespace App\Domains\Repository\Http\Requests;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepositoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::enum(GitProvider::class)],
            'repo_identifier' => ['required', 'string', 'max:500'],
            'default_branch' => ['nullable', 'string', 'max:255'],
        ];
    }
}
