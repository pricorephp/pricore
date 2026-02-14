<?php

namespace App\Http\Requests\Settings;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserGitCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $provider = GitProvider::tryFrom($this->input('provider'));

        $baseRules = [
            'provider' => ['required', 'string', Rule::enum(GitProvider::class)],
        ];

        $credentialRules = match ($provider) {
            GitProvider::GitHub => [
                'credentials.token' => ['required', 'string'],
            ],
            GitProvider::GitLab => [
                'credentials.token' => ['required', 'string'],
                'credentials.url' => ['nullable', 'url'],
            ],
            GitProvider::Bitbucket => [
                'credentials.username' => ['required', 'string'],
                'credentials.app_password' => ['required', 'string'],
            ],
            GitProvider::Git => [
                'credentials.ssh_key' => ['required', 'string'],
            ],
            null => [],
        };

        return array_merge($baseRules, $credentialRules);
    }
}
