<?php

namespace App\Domains\Repository\Http\Requests;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreRepositoryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::enum(GitProvider::class)],
            'repositories' => ['required', 'array', 'min:1', 'max:50'],
            'repositories.*.repo_identifier' => ['required', 'string', 'max:500'],
        ];
    }
}
