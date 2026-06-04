<?php

namespace App\Actions\Package;

use App\Models\Package\ClientPackage;
use App\Models\User\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListMyClientPackagesAction
{
    public function __invoke(User $client, array $filters = []): LengthAwarePaginator
    {
        $query = ClientPackage::query()
            ->with(['packageProduct.service', 'service'])
            ->where('client_id', $client->id);

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['service_id'] ?? null) {
            $query->where('service_id', $filters['service_id']);
        }

        if ($filters['professional_id'] ?? null) {
            $query->where('professional_id', $filters['professional_id']);
        }

        return $query
            ->latest('purchased_at')
            ->paginate(min((int) ($filters['per_page'] ?? 10), 50));
    }
}
