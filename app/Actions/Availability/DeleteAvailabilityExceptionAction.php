<?php

namespace App\Actions\Availability;

use App\Models\Availability\AvailabilityException;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class DeleteAvailabilityExceptionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        AvailabilityException $availabilityException
    ): void {
        $availabilityException->delete();

        $this->activityLogger->record(
            event: ActivityLogEvent::AvailabilityExceptionDeleted,
            entityType: 'availability_exception',
            entityId: $availabilityException->id,
            entityOwnerId: $availabilityException->service?->professional_id,
            metadata: [
                'service_id' => $availabilityException->service_id,
                'exception_date' => $availabilityException->exception_date,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );
    }
}
