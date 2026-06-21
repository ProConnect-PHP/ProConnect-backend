<?php

namespace App\Actions\Availability;

use App\Exceptions\ApiException;
use App\Http\Requests\Availability\StoreAvailabilityExceptionRequest;
use App\Models\Availability\AvailabilityException;
use App\Models\Service\Service;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;

class StoreAvailabilityExceptionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        Service $service,
        StoreAvailabilityExceptionRequest $request
    ): AvailabilityException {
        $data = $request->validated();

        $exceptionDate = $data['exception_date'];

        $alreadyExists = AvailabilityException::query()
            ->where('service_id', $service->id)
            ->whereDate('exception_date', $exceptionDate)
            ->exists();

        if ($alreadyExists) {
            throw new ApiException(
                error: 'AvailabilityExceptionAlreadyExists',
                message: 'Ya existe una excepción de disponibilidad para ese día.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $exception = AvailabilityException::create([
            ...$data,
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
                'exception_date' => $exception->exception_date?->toDateString(),
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
