<?php

namespace App\Actions\Service;

use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service\Service;
use App\Models\User\User;
use App\Services\Notification\NotificationService;

class UpdateServiceAction
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function __invoke(UpdateServiceRequest $request, Service $service): Service
    {
        $service->update($request->validated());
        $service = $service->refresh();

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