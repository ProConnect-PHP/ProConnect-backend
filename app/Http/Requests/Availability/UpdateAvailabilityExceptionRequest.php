<?php

namespace App\Http\Requests\Availability;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exception_date' => ['sometimes', 'required', 'date'],
            'is_unavailable' => ['sometimes', 'boolean'],
            'alt_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'alt_end' => ['sometimes', 'nullable', 'date_format:H:i', 'after:alt_start'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
