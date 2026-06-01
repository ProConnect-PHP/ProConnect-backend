<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\UpdateAvailabilityRuleRequest;
use App\Models\Availability\AvailabilityRule;

class UpdateAvailabilityRuleAction
{
    public function __invoke(
        AvailabilityRule $availabilityRule,
        UpdateAvailabilityRuleRequest $request
    ): AvailabilityRule {
        $availabilityRule->update(
            $request->validated()
        );

        return $availabilityRule->refresh();
    }
}
