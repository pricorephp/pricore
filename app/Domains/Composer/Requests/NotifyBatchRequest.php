<?php

namespace App\Domains\Composer\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotifyBatchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'downloads' => ['required', 'array', 'min:1'],
            'downloads.*.name' => ['required', 'string'],
            'downloads.*.version' => ['required', 'string'],
        ];
    }
}
