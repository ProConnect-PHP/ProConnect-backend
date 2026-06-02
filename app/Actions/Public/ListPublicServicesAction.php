<?php

namespace App\Actions\Public;

use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Support\Geo\Haversine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPublicServicesAction
{
    public function __invoke(array $filters): LengthAwarePaginator
    {
        $today = Carbon::today()->toDateString();

        $query = Service::query()
            ->with(['professional.user', 'company'])
            ->where('is_active', true)
            ->whereHas('professional')
            ->whereHas('professional.user')
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query
                    ->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            });

        $this->applySearch($query, $filters);
        $this->applyBasicFilters($query, $filters);
        $this->applyAvailabilityFilter($query, $filters);
        $hasGeoFilter = $this->applyGeoFilter($query, $filters);
        $this->applySort($query, $filters, $hasGeoFilter);

        $perPage = min((int) ($filters['per_page'] ?? 12), 50);

        return $query->paginate($perPage);
    }

    private function applySearch($query, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '') {
            return;
        }

        $query->where(function ($query) use ($search) {
            $query
                ->where('services.name', 'ILIKE', "%{$search}%")
                ->orWhere('services.description', 'ILIKE', "%{$search}%")
                ->orWhereHas('professional.user', function ($query) use ($search) {
                    $query->where('name', 'ILIKE', "%{$search}%");
                })
                ->orWhereHas('professional', function ($query) use ($search) {
                    $query->where('bio', 'ILIKE', "%{$search}%");
                })
                ->orWhereHas('company', function ($query) use ($search) {
                    $query
                        ->where('is_private', false)
                        ->where('commercial_name', 'ILIKE', "%{$search}%");
                });
        });
    }

    private function applyBasicFilters($query, array $filters): void
    {
        if ($filters['modality'] ?? null) {
            $query->where('services.modality', $filters['modality']);
        }

        if (array_key_exists('min_price', $filters)) {
            $query->where('services.price', '>=', $filters['min_price']);
        }

        if (array_key_exists('max_price', $filters)) {
            $query->where('services.price', '<=', $filters['max_price']);
        }

        if ($filters['duration_minutes'] ?? null) {
            $query->where('services.duration_minutes', $filters['duration_minutes']);
        }

        if (array_key_exists('is_verified', $filters)) {
            $query->whereHas('professional', function ($query) use ($filters) {
                $query->where(
                    'is_verified',
                    filter_var($filters['is_verified'], FILTER_VALIDATE_BOOLEAN)
                );
            });
        }
    }

    private function applyAvailabilityFilter($query, array $filters): void
    {
        if (! ($filters['available_date'] ?? null)) {
            return;
        }

        $availableDate = Carbon::parse($filters['available_date'])->toDateString();
        $dayOfWeek = Carbon::parse($availableDate)->dayOfWeekIso;

        $query
            ->whereHas('availabilityRules', function ($query) use ($dayOfWeek) {
                $query
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_active', true);
            })
            ->whereDoesntHave('availabilityExceptions', function ($query) use ($availableDate) {
                $query
                    ->whereDate('exception_date', $availableDate)
                    ->where('is_unavailable', true);
            });
    }

    private function applyGeoFilter($query, array $filters): bool
    {
        if (
            ! array_key_exists('latitude', $filters)
            || ! array_key_exists('longitude', $filters)
            || ! array_key_exists('radius_km', $filters)
        ) {
            return false;
        }

        $latitude = (float) $filters['latitude'];
        $longitude = (float) $filters['longitude'];
        $radius = (float) $filters['radius_km'];
        $expression = Haversine::distanceExpression();
        $bindings = Haversine::bindings($latitude, $longitude);

        $query
            ->whereNotNull('services.latitude')
            ->whereNotNull('services.longitude')
            ->select('services.*')
            ->selectRaw($expression.' AS distance_km', $bindings)
            ->whereRaw($expression.' <= ?', [...$bindings, $radius]);

        return true;
    }

    private function applySort($query, array $filters, bool $hasGeoFilter): void
    {
        $sort = $filters['sort'] ?? null;

        match ($sort) {
            'price_asc' => $query->orderBy('services.price'),
            'price_desc' => $query->orderByDesc('services.price'),
            'duration_asc' => $query->orderBy('services.duration_minutes'),
            'duration_desc' => $query->orderByDesc('services.duration_minutes'),
            'rating_desc' => $query->orderByDesc(
                ProfessionalProfile::query()
                    ->select('avg_rating')
                    ->whereColumn('professional_profiles.id', 'services.professional_id')
                    ->limit(1)
            ),
            default => $hasGeoFilter
                ? $query->orderBy('distance_km')
                : $query->latest('services.created_at'),
        };
    }
}
