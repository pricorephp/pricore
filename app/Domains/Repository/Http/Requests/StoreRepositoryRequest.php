<?php

namespace App\Domains\Repository\Http\Requests;

use App\Domains\Repository\Contracts\Enums\GitProvider;
use App\Domains\Repository\Rules\ValidRepositoryIdentifier;
use App\Models\Organization;
use App\Rules\AllowedOutboundUrl;
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
        $provider = GitProvider::tryFrom((string) $this->input('provider'));

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::enum(GitProvider::class)],
            'repo_identifier' => [
                'required',
                'string',
                'max:500',
                new ValidRepositoryIdentifier($provider),
                ...($provider === GitProvider::Git ? [app(AllowedOutboundUrl::class)] : []),
            ],
            'default_branch' => ['nullable', 'string', 'max:255'],
            'ssh_key_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('organization_ssh_keys', 'uuid')
                    ->where('organization_uuid', $this->organizationUuid()),
            ],
        ];
    }

    /**
     * SSH keys belong to an organization — one from another organization must never be
     * selectable here, or its private key could be used to clone that organization's
     * repositories.
     */
    private function organizationUuid(): ?string
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization ? $organization->uuid : null;
    }
}
