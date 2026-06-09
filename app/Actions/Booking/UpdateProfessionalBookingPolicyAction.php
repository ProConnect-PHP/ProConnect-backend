<?php

namespace App\Actions\Booking;

use App\Models\Booking\ProfessionalBookingPolicy;
use App\Models\User\ProfessionalProfile;

class UpdateProfessionalBookingPolicyAction
{
    public function __construct(
        private readonly EnsureDefaultProfessionalBookingPolicyAction $ensureDefaults
    ) {}

    public function __invoke(
        ProfessionalProfile $professional,
        array $data
    ): ProfessionalBookingPolicy {
        $policy = ($this->ensureDefaults)($professional);
        $policy->update($data);

        return $policy->refresh()
            ->load(['reminderRules' => fn ($query) => $query->orderByDesc('minutes_before_start')]);
    }
}
