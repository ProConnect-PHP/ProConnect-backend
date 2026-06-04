<?php

namespace App\Actions\Package;

use App\Models\Package\PackageProduct;

class DeletePackageProductAction
{
    public function __invoke(PackageProduct $packageProduct): void
    {
        $packageProduct->delete();
    }
}
