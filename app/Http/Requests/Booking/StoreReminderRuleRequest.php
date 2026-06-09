<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\Booking\Concerns\ValidatesReminderRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRuleRequest extends FormRequest
{
    use ValidatesReminderRule;

    public function authorize(): bool
    {
        return auth('user_jwt')->user()?->professionalProfile !== null;
    }

    public function rules(): array
    {
        $professionalId = auth('user_jwt')->user()?->professionalProfile?->id;
        $rules = $this->reminderRuleRules();
        $rules['minutes_before_start'][] = Rule::unique(
            'professional_booking_reminder_rules',
            'minutes_before_start'
        )->where(fn ($query) => $query->where('professional_id', $professionalId));

        return $rules;
    }
}
