<?php

namespace App\Actions\Package;

use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPublicPackageProductsAction
{
    public function __invoke(array $filters = [], ?Service $service = null): LengthAwarePaginator
    {
        $query = PackageProduct::query()
            ->with(['service', 'professional.user'])
            ->where('is_active', true)
            ->whereHas('professional.user');

        if ($service) {
            $query->where('service_id', $service->id);
        }

        if ($filters['service_id'] ?? null) {
            $query->where('service_id', $filters['service_id']);
        }

        if ($filters['professional_id'] ?? null) {
            $query->where('professional_id', $filters['professional_id']);
        }

        if (array_key_exists('min_price', $filters)) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (array_key_exists('max_price', $filters)) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if ($filters['sessions_count'] ?? null) {
            $query->where('sessions_count', $filters['sessions_count']);
        }

        return $query
            ->latest()
            ->paginate(min((int) ($filters['per_page'] ?? 12), 50));
    }
}
