<?php

namespace App\Domains\Repository\Http\Requests;

use App\Domains\Repository\Http\Requests\Concerns\HasPackagePaths;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRepositoryRequest extends FormRequest
{
    use HasPackagePaths;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->packagePathRules();
    }
}
