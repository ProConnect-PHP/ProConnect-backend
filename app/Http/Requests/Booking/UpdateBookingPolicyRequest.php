<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('user_jwt')->user()?->professionalProfile !== null;
    }

    public function rules(): array
    {
        return [
            'allow_client_cancellation' => ['required', 'boolean'],
            'cancellation_cutoff_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'allow_client_rescheduling' => ['required', 'boolean'],
            'rescheduling_cutoff_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'reminders_enabled' => ['required', 'boolean'],
            'cancellation_policy_text' => ['nullable', 'string', 'max:2000'],
            'rescheduling_policy_text' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
