<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\StoreAvailabilityRuleRequest;
use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;

class StoreAvailabilityRuleAction
{
    public function __invoke(
        Service $service,
        StoreAvailabilityRuleRequest $request
    ): AvailabilityRule {
        return AvailabilityRule::create([
            ...$request->validated(),
            'service_id' => $service->id,
        ]);
    }
}
