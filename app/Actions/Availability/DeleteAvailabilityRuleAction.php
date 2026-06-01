<?php

namespace App\Actions\Availability;

use App\Models\Availability\AvailabilityRule;

class DeleteAvailabilityRuleAction
{
    public function __invoke(
        AvailabilityRule $availabilityRule
    ): void {
        $availabilityRule->delete();
    }
}
