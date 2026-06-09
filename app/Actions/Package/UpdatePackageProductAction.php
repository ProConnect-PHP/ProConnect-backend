<?php

namespace App\Actions\Package;

use App\Models\Package\ClientPackage;
use App\Exceptions\ApiException;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use Symfony\Component\HttpFoundation\Response;

class UpdatePackageProductAction
{
    public function __invoke(PackageProduct $packageProduct, array $data): PackageProduct
    {
        //verifica que no haya sido comprado
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

        return $packageProduct->refresh()->load(['service', 'professional.user']);
    }
}
