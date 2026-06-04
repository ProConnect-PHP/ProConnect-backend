<?php

namespace App\Policies;

use App\Models\Package\ClientPackage;
use App\Models\User\User;

class ClientPackagePolicy
{
    public function view(User $user, ClientPackage $clientPackage): bool
    {
        return $clientPackage->client_id === $user->id
            || $user->professionalProfile?->id === $clientPackage->professional_id;
    }

    public function use(User $user, ClientPackage $clientPackage): bool
    {
        return $clientPackage->client_id === $user->id;
    }
}
