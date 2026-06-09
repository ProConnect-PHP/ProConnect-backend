<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\Booking\Concerns\ValidatesReminderRule;
use App\Models\Booking\ProfessionalBookingReminderRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRuleRequest extends FormRequest
{
    use ValidatesReminderRule;

    public function authorize(): bool
    {
        $professionalId = auth('user_jwt')->user()?->professionalProfile?->id;
        $rule = $this->route('reminderRule');

        return $rule instanceof ProfessionalBookingReminderRule
            && $professionalId !== null
            && $rule->professional_id === $professionalId;
    }

    public function rules(): array
    {
        $professionalId = auth('user_jwt')->user()?->professionalProfile?->id;
        $rule = $this->route('reminderRule');
        $rules = $this->reminderRuleRules();
        $rules['minutes_before_start'][] = Rule::unique(
            'professional_booking_reminder_rules',
            'minutes_before_start'
        )
            ->where(fn ($query) => $query->where('professional_id', $professionalId))
            ->ignore($rule?->id);

        return $rules;
    }
}
