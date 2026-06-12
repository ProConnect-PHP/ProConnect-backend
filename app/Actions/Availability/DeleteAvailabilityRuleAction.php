<?php

namespace App\Actions\Availability;

use App\Models\Availability\AvailabilityRule;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class DeleteAvailabilityRuleAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        AvailabilityRule $availabilityRule
    ): void {
        $availabilityRule->delete();

        $this->activityLogger->record(
            event: ActivityLogEvent::AvailabilityDeleted,
            entityType: 'availability_rule',
            entityId: $availabilityRule->id,
            entityOwnerId: $availabilityRule->service?->professional_id,
            metadata: [
                'service_id' => $availabilityRule->service_id,
                'day_of_week' => $availabilityRule->day_of_week,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );
    }
}
