<?php

namespace App\Actions\Booking;

use App\Enums\Booking\BookingReminderDeliveryStatus;
use App\Models\Booking\ProfessionalBookingReminderRule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateProfessionalBookingReminderRuleAction
{
    public function __invoke(
        ProfessionalBookingReminderRule $rule,
        array $data
    ): ProfessionalBookingReminderRule {
        try {
            return DB::transaction(function () use ($rule, $data): ProfessionalBookingReminderRule {
                $rule->update($data);

                $rule->deliveries()
                    ->where('status', '!=', BookingReminderDeliveryStatus::Sent->value)
                    ->delete();

                return $rule->refresh();
            });
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
