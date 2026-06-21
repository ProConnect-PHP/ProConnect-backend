<?php

namespace App\Actions\Service;

use App\Exceptions\ApiException;
use App\Models\Service\Service;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;

class DeleteServiceAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(Service $service): void
    {
        if ($service->bookings()->exists()) {

            throw new ApiException(
                error: 'ServiceHasBookings',
                message: 'No se puede eliminar un servicio que tiene reservas asociadas.',
                status: Response::HTTP_CONFLICT
            );
        }

        $service->delete();

        $this->activityLogger->record(
            event: ActivityLogEvent::ServiceDeleted,
            entityType: 'service',
            entityId: $service->id,
            entityOwnerId: $service->professional_id,
            metadata: [
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );
    }
}
