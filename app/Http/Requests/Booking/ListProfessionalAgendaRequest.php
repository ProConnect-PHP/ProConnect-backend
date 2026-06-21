<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProfessionalAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('user_jwt')->check();
    }

    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(['week', 'month'])],
            'date' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => $this->input('view') === 'month'),
            ],
            'from' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => $this->input('view', 'week') === 'week'
                    && blank($this->input('date'))),
            ],
            'to' => [
                'nullable',
                'date',
                'after:from',
                Rule::requiredIf(fn (): bool => $this->input('view', 'week') === 'week'
                    && blank($this->input('date'))),
            ],
            'status' => ['nullable', 'string'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'La fecha es obligatoria para la vista mensual.',
            'from.required' => 'La fecha inicial es obligatoria.',
            'to.required' => 'La fecha final es obligatoria.',
            'to.after' => 'La fecha final debe ser posterior a la fecha inicial.',
        ];
    }
}
