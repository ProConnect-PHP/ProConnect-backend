<?php

namespace App\Actions\Package;

use App\Exceptions\ApiException;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use Symfony\Component\HttpFoundation\Response;

class CreatePackageProductAction
{
    public function __invoke(ProfessionalProfile $professionalProfile, array $data): PackageProduct
    {
        $this->ensureServiceBelongsToProfessional($professionalProfile, $data['service_id'] ?? null);

        return PackageProduct::create([
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
