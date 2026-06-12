<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\UpdateAvailabilityRuleRequest;
use App\Models\Availability\AvailabilityRule;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class UpdateAvailabilityRuleAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        AvailabilityRule $availabilityRule,
        UpdateAvailabilityRuleRequest $request
    ): AvailabilityRule {
        $data = $request->validated();
        $availabilityRule->update($data);

        $availabilityRule = $availabilityRule->refresh();

        $this->activityLogger->record(
            event: ActivityLogEvent::AvailabilityUpdated,
            entityType: 'availability_rule',
            entityId: $availabilityRule->id,
            entityOwnerId: $availabilityRule->service?->professional_id,
            metadata: [
                'service_id' => $availabilityRule->service_id,
                'changed_fields' => array_keys($data),
                'day_of_week' => $availabilityRule->day_of_week,
                'start_time' => $availabilityRule->start_time,
                'end_time' => $availabilityRule->end_time,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $availabilityRule;
    }
}
