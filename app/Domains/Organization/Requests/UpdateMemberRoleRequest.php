<?php

namespace App\Domains\Organization\Requests;

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->can('manageMembers', $this->route('organization'));
    }

    /**
     * @return array<string, array<int, string|Enum>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(array_map(fn ($role) => $role->value, OrganizationRole::assignableRoles()))],
        ];
    }
}
