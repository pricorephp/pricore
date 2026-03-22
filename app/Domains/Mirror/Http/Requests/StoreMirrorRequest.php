<?php

namespace App\Domains\Mirror\Http\Requests;

use App\Domains\Mirror\Contracts\Enums\MirrorAuthType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMirrorRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'auth_type' => ['required', Rule::enum(MirrorAuthType::class)],
            'username' => ['required_if:auth_type,basic', 'nullable', 'string', 'max:255'],
            'password' => ['required_if:auth_type,basic', 'nullable', 'string', 'max:1024'],
            'token' => ['required_if:auth_type,bearer', 'nullable', 'string', 'max:1024'],
            'mirror_dist' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function authCredentials(): ?array
    {
        return match (MirrorAuthType::from($this->validated('auth_type'))) {
            MirrorAuthType::Basic => [
                'username' => $this->validated('username'),
                'password' => $this->validated('password'),
            ],
            MirrorAuthType::Bearer => [
                'token' => $this->validated('token'),
            ],
            MirrorAuthType::None => null,
        };
    }
}
