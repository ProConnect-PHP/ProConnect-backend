<?php

namespace App\Actions\Package;

use App\Models\Package\PackageProduct;
use App\Models\Package\ClientPackage; 
use App\Exceptions\ApiException; 
use Symfony\Component\HttpFoundation\Response; 

class DeletePackageProductAction
{
    public function __invoke(PackageProduct $packageProduct): void
    {
        // REGLA CU17: Si ya fue vendido, no se elimina; se desactiva (Stock a 0 / is_active false)
        $hasBeenPurchased = ClientPackage::query()->where('package_product_id', $packageProduct->id)->exists();

        if ($hasBeenPurchased) {
            $packageProduct->update(['is_active' => false]);
            return; // Cortamos acá, no lo borramos
        }

        // Si nunca se vendió, se puede borrar normalmente
        $packageProduct->delete();
    }
}