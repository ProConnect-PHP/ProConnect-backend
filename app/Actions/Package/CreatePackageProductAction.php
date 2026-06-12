<?php

namespace App\Actions\Package;

use App\Exceptions\ApiException;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;

class CreatePackageProductAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(ProfessionalProfile $professionalProfile, array $data): PackageProduct
    {
        $this->ensureServiceBelongsToProfessional($professionalProfile, $data['service_id'] ?? null);

        $packageProduct = PackageProduct::create([
            'professional_id' => $professionalProfile->id,
            'service_id' => $data['service_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sessions_count' => $data['sessions_count'],
            'price' => $data['price'],
            'currency' => $data['currency'] ?? config('proconnect.payments.currency', 'UYU'),
            'validity_days' => $data['validity_days'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ])->load(['service', 'professional.user']);

        $this->activityLogger->record(
            event: ActivityLogEvent::PackageProductCreated,
            entityType: 'package_product',
            entityId: $packageProduct->id,
            entityOwnerId: $professionalProfile->id,
            metadata: [
                'package_product_id' => $packageProduct->id,
                'professional_id' => $packageProduct->professional_id,
                'service_id' => $packageProduct->service_id,
                'price' => $packageProduct->price,
                'currency' => $packageProduct->currency,
                'sessions_count' => $packageProduct->sessions_count,
                'validity_days' => $packageProduct->validity_days,
                'is_active' => $packageProduct->is_active,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $packageProduct;
    }

    private function ensureServiceBelongsToProfessional(ProfessionalProfile $professionalProfile, ?string $serviceId): void
    {
        if (! $serviceId) {
            return;
        }

        $ownsService = Service::query()
            ->whereKey($serviceId)
            ->where('professional_id', $professionalProfile->id)
            ->exists();

        if (! $ownsService) {
            throw new ApiException(
                error: 'Forbidden',
                message: 'No puedes crear paquetes para este servicio.',
                status: Response::HTTP_FORBIDDEN
            );
        }
    }
}
