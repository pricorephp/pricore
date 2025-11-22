<?php

namespace App\Domains\Organization\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('update', $this->route('organization'));
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        /** @var \App\Models\Organization|null $organization */
        $organization = $this->route('organization');
        $user = $this->user();
        $isOwner = $organization !== null && $user !== null && $organization->owner_uuid === $user->uuid;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($isOwner) {
            $rules['slug'] = [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('organizations', 'slug')->ignore($organization->uuid, 'uuid'),
            ];
        }

        return $rules;
    }
}
