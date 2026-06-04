<?php

namespace App\Policies;

use App\Models\Package\PackageProduct;
use App\Models\User\User;

class PackageProductPolicy
{
    public function manage(User $user, PackageProduct $packageProduct): bool
    {
        return $user->professionalProfile?->id === $packageProduct->professional_id;
    }

    public function update(User $user, PackageProduct $packageProduct): bool
    {
        return $this->manage($user, $packageProduct);
    }

    public function delete(User $user, PackageProduct $packageProduct): bool
    {
        return $this->manage($user, $packageProduct);
    }
}
