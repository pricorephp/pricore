<?php

namespace App\Http\Requests\Settings;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Rules\SafeGitLabUrl;
use App\Models\UserGitCredential;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserGitCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var UserGitCredential|null $credential */
        $credential = $this->route('credential');

        return $this->user() !== null
            && $credential !== null
            && $credential->user_uuid === $this->user()->uuid;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var UserGitCredential|null $credential */
        $credential = $this->route('credential');

        $provider = $credential?->provider;

        return match ($provider) {
            GitProvider::GitHub => [
                'credentials.token' => ['required', 'string'],
            ],
            GitProvider::GitLab => [
                'credentials.token' => ['required', 'string'],
                'credentials.url' => ['nullable', 'url', app(SafeGitLabUrl::class)],
            ],
            GitProvider::Bitbucket => [
                'credentials.email' => ['required', 'email'],
                'credentials.api_token' => ['required', 'string'],
            ],
            default => [
                'credentials.ssh_key' => ['required', 'string'],
            ],
        };
    }
}
