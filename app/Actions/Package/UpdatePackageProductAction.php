<?php

namespace App\Actions\Package;

use App\Exceptions\ApiException;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;

class UpdatePackageProductAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(PackageProduct $packageProduct, array $data): PackageProduct
    {
        // verifica que no haya sido comprado
        $hasBeenPurchased = ClientPackage::query()->where('package_product_id', $packageProduct->id)->exists();
        if ($hasBeenPurchased) {
            $restrictedFields = ['service_id', 'name', 'description', 'sessions_count', 'price', 'currency', 'validity_days'];

            foreach ($restrictedFields as $field) {
                if (array_key_exists($field, $data) && $data[$field] != $packageProduct->$field) {
                    throw new ApiException(
                        error: 'PackageAlreadySold',
                        message: 'Este paquete ya ha sido vendido. No puedes modificar sus datos base.',
                        status: Response::HTTP_CONFLICT
                    );
                }
            }
        }

        if (array_key_exists('service_id', $data) && $data['service_id'] !== null) {
            $ownsService = Service::query()
                ->whereKey($data['service_id'])
                ->where('professional_id', $packageProduct->professional_id)
                ->exists();

            if (! $ownsService) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes asociar este paquete a ese servicio.',
                    status: Response::HTTP_FORBIDDEN
                );
            }
        }

        $packageProduct->update($data);

        $packageProduct = $packageProduct->refresh()->load(['service', 'professional.user']);

        $this->activityLogger->record(
            event: ActivityLogEvent::PackageProductUpdated,
            entityType: 'package_product',
            entityId: $packageProduct->id,
            entityOwnerId: $packageProduct->professional_id,
            metadata: [
                'package_product_id' => $packageProduct->id,
                'professional_id' => $packageProduct->professional_id,
                'changed_fields' => array_keys($data),
                'is_active' => $packageProduct->is_active,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $packageProduct;
    }
}
