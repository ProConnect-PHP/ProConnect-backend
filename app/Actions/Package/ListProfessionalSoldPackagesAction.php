<?php

namespace App\Actions\Package;

use App\Models\Package\ClientPackage;
use App\Models\User\ProfessionalProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListProfessionalSoldPackagesAction
{
    public function __invoke(ProfessionalProfile $professionalProfile, array $filters = []): LengthAwarePaginator
    {
        $query = ClientPackage::query()
            ->with(['packageProduct.service', 'service', 'client'])
            ->where('professional_id', $professionalProfile->id);

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['service_id'] ?? null) {
            $query->where('service_id', $filters['service_id']);
        }

        return $query
            ->latest('purchased_at')
            ->paginate(min((int) ($filters['per_page'] ?? 10), 50));
    }
}
