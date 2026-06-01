<?php

namespace App\Http\Requests\Availability;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvailabilityExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exception_date' => ['required', 'date'],
            'is_unavailable' => ['sometimes', 'boolean'],
            'alt_start' => ['nullable', 'date_format:H:i'],
            'alt_end' => ['nullable', 'date_format:H:i', 'after:alt_start'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
