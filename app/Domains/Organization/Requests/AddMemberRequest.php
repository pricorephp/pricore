<?php

namespace App\Domains\Organization\Requests;

use App\Domains\Organization\Contracts\Enums\OrganizationRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMemberRequest extends FormRequest
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
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Enum>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::enum(OrganizationRole::class)],
        ];
    }
}
