<?php

namespace App\Http\Resources\Booking;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalBookingPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'allow_client_cancellation' => $this->allow_client_cancellation,
            'cancellation_cutoff_minutes' => $this->cancellation_cutoff_minutes,
            'allow_client_rescheduling' => $this->allow_client_rescheduling,
            'rescheduling_cutoff_minutes' => $this->rescheduling_cutoff_minutes,
            'late_tolerance_minutes' => $this->late_tolerance_minutes,
            'reminders_enabled' => $this->reminders_enabled,
            'cancellation_policy_text' => $this->cancellation_policy_text,
            'rescheduling_policy_text' => $this->rescheduling_policy_text,
            'reminder_rules' => ProfessionalBookingReminderRuleResource::collection(
                $this->whenLoaded('reminderRules')
            ),
        ];
    }
}
