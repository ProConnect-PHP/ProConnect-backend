<?php

namespace App\Policies;

use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Models\User\User;

class ProfessionalBookingReminderRulePolicy
{
    public function update(User $user, ProfessionalBookingReminderRule $rule): bool
    {
        return $user->professionalProfile?->id === $rule->professional_id;
    }

    public function delete(User $user, ProfessionalBookingReminderRule $rule): bool
    {
        return $this->update($user, $rule);
    }
}
