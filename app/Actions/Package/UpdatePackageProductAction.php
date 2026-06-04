<?php

namespace App\Actions\Package;

use App\Exceptions\ApiException;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use Symfony\Component\HttpFoundation\Response;

class UpdatePackageProductAction
{
    public function __invoke(PackageProduct $packageProduct, array $data): PackageProduct
    {
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
