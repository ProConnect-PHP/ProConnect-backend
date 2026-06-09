<?php

namespace App\Actions\Booking;

use App\Models\Booking\ProfessionalBookingReminderRule;

class DeleteProfessionalBookingReminderRuleAction
{
    public function __invoke(ProfessionalBookingReminderRule $rule): void
    {
        $rule->delete();
    }
}
