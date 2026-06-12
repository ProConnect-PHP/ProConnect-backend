<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\StoreAvailabilityRuleRequest;
use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class StoreAvailabilityRuleAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        Service $service,
        StoreAvailabilityRuleRequest $request
    ): AvailabilityRule {
        $rule = AvailabilityRule::create([
            ...$request->validated(),
            'service_id' => $service->id,
        ]);

        $this->activityLogger->record(
            event: ActivityLogEvent::AvailabilityCreated,
            entityType: 'availability_rule',
            entityId: $rule->id,
            entityOwnerId: $service->professional_id,
            metadata: [
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
                'day_of_week' => $rule->day_of_week,
                'start_time' => $rule->start_time,
                'end_time' => $rule->end_time,
                'is_active' => $rule->is_active,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $rule;
    }
}
