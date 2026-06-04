<?php

namespace App\Http\Requests\Package;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'sessions_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'price' => ['sometimes', 'required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
