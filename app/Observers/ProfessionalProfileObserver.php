<?php

namespace App\Observers;

use App\Actions\Booking\EnsureDefaultProfessionalBookingPolicyAction;
use App\Models\User\ProfessionalProfile;

class ProfessionalProfileObserver
{
    public function __construct(
        private readonly EnsureDefaultProfessionalBookingPolicyAction $ensureDefaults
    ) {}

    public function created(ProfessionalProfile $professional): void
    {
        ($this->ensureDefaults)($professional);
    }
}
