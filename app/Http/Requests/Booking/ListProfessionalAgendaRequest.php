<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class ListProfessionalAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('user_jwt')->check();
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'status' => ['nullable', 'string'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'from.required' => 'La fecha inicial es obligatoria.',
            'to.required' => 'La fecha final es obligatoria.',
            'to.after' => 'La fecha final debe ser posterior a la fecha inicial.',
        ];
    }
}
