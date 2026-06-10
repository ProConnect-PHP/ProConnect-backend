<?php

namespace App\Policies;

use App\Models\Service\Service;
use App\Models\User\User;

class ServicePolicy
{
    public function create(User $user): bool
    {
        return $user->isProfessional();
    }

    public function view(User $user, Service $service): bool
    {
        return $this->ownsService($user, $service);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->ownsService($user, $service);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->ownsService($user, $service);
    }

    private function ownsService(User $user, Service $service): bool
    {
        return $user->professionalProfile?->id === $service->professional_id;
    }
}
