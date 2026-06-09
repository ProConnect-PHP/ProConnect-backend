<?php

namespace App\Actions\Booking;

use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateProfessionalBookingReminderRuleAction
{
    public function __invoke(
        ProfessionalProfile $professional,
        array $data
    ): ProfessionalBookingReminderRule {
        try {
            return $professional->reminderRules()->create($data);
        } catch (QueryException $exception) {
            if (
                (string) $exception->getCode() !== '23505'
                || ! str_contains(
                    $exception->getMessage(),
                    'professional_reminder_rules_minutes_unique'
                )
            ) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'minutes_before_start' => 'Ya existe una regla con esa anticipación.',
            ]);
        }
    }
}
