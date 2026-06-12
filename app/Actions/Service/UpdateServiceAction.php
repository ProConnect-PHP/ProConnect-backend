<?php

namespace App\Actions\Service;

use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service\Service;
use App\Services\Notification\NotificationService;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class UpdateServiceAction
{
    public function __construct(
        private NotificationService $notificationService,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(UpdateServiceRequest $request, Service $service): Service
    {
        $data = $request->validated();
        $service->update($data);
        $service = $service->refresh();

        $this->activityLogger->record(
            event: ActivityLogEvent::ServiceUpdated,
            entityType: 'service',
            entityId: $service->id,
            entityOwnerId: $service->professional_id,
            metadata: [
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
                'changed_fields' => array_keys($data),
                'modality' => $service->modality,
                'price' => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'is_active' => $service->is_active,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        $clients = $this->resolveClients($service);

        foreach ($clients as $client) {
            $this->notificationService->send(
                $client,
                'service_updated',
                'Servicio actualizado',
                "El servicio '{$service->name}' que reservaste fue actualizado."
            );
        }

        return $service;
    }

    private function resolveClients(Service $service): array
    {
        return $service->bookings()
            ->whereNotNull('client_id')
            ->with('client')
            ->get()
            ->pluck('client')
            ->unique('id')
            ->values()
            ->all();
    }
}
