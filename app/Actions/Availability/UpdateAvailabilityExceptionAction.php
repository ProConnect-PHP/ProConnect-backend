<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\UpdateAvailabilityExceptionRequest;
use App\Models\Availability\AvailabilityException;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class UpdateAvailabilityExceptionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        AvailabilityException $availabilityException,
        UpdateAvailabilityExceptionRequest $request
    ): AvailabilityException {
        $data = $request->validated();
        $availabilityException->update($data);

        $availabilityException = $availabilityException->refresh();

        $this->activityLogger->record(
            event: ActivityLogEvent::AvailabilityExceptionUpdated,
            entityType: 'availability_exception',
            entityId: $availabilityException->id,
            entityOwnerId: $availabilityException->service?->professional_id,
            metadata: [
                'service_id' => $availabilityException->service_id,
                'changed_fields' => array_keys($data),
                'exception_date' => $availabilityException->exception_date,
                'is_unavailable' => $availabilityException->is_unavailable,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $availabilityException;
    }
}
