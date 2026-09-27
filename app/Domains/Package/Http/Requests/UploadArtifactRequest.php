<?php

namespace App\Domains\Package\Http\Requests;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadArtifactRequest extends FormRequest
{
    /**
     * Checked before validation, so members learn nothing from error messages.
     * Token requests carry no user session; the composer.publish middleware
     * has already authorized those.
     */
    public function authorize(): bool
    {
        if ($this->attributes->has('accessToken')) {
            return true;
        }

        $organization = $this->route('organization');

        return $organization instanceof Organization
            && (bool) $this->user()?->can('managePackages', $organization);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKilobytes = config('pricore.uploads.max_size') * 1024;

        return [
            'archive' => ['required', 'file', "max:{$maxKilobytes}"],
            'version' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archive.max' => 'The archive may not be larger than '.config('pricore.uploads.max_size').' MB.',
        ];
    }
}
