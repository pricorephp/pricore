<?php

namespace App\Domains\Organization\Http\Requests;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Models\OrganizationGitCredential;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGitCredentialRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('viewSettings', $this->route('organization'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var OrganizationGitCredential|null $credential */
        $credential = $this->route('credential');

        $provider = $credential?->provider;

        return match ($provider) {
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
            default => [
                'credentials.ssh_key' => ['required', 'string'],
            ],
        };
    }
}
