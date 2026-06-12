<?php

namespace App\Actions\Service;

use App\Exceptions\ApiException;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Models\Service\Service;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Http\Response;

class StoreServiceAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(StoreServiceRequest $request): Service
    {
        $data = $request->validated();
        $user = auth('user_jwt')->user();
        $professionalProfile = $user->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Professional profile is required to create services.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        if (! empty($data['company_id'])) {
            if (! $professionalProfile->companies()
                ->whereKey($data['company_id'])
                ->exists()) {
                throw new ApiException(
                    error: 'CompanyForbidden',
                    message: 'No puedes asociar una empresa que no te pertenece.',
                    status: Response::HTTP_FORBIDDEN
                );
            }
        }

        $service = Service::create([
            ...$data,
            'professional_id' => $professionalProfile->id,
        ]);

        $this->activityLogger->record(
            event: ActivityLogEvent::ServiceCreated,
            entityType: 'service',
            entityId: $service->id,
            entityOwnerId: $professionalProfile->id,
            metadata: [
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
                'company_id' => $service->company_id,
                'modality' => $service->modality,
                'price' => $service->price,
                'duration_minutes' => $service->duration_minutes,
                'is_active' => $service->is_active,
            ],
            actor: $user,
            actingAs: ActivityLogActorMode::Professional,
        );

        return $service;
    }
}
