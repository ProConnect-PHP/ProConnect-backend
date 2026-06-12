<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\StoreAvailabilityExceptionRequest;
use App\Models\Availability\AvailabilityException;
use App\Models\Service\Service;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class StoreAvailabilityExceptionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        Service $service,
        StoreAvailabilityExceptionRequest $request
    ): AvailabilityException {
        $exception = AvailabilityException::create([
            ...$request->validated(),
            'service_id' => $service->id,
        ]);

        $this->activityLogger->record(
            event: ActivityLogEvent::AvailabilityExceptionCreated,
            entityType: 'availability_exception',
            entityId: $exception->id,
            entityOwnerId: $service->professional_id,
            metadata: [
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
                'exception_date' => $exception->exception_date,
                'is_unavailable' => $exception->is_unavailable,
                'alt_start' => $exception->alt_start,
                'alt_end' => $exception->alt_end,
                'reason' => $exception->reason,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $exception;
    }
}
