<?php

namespace App\Actions\Booking;

use App\Models\Booking\Booking;
use App\Models\User\ProfessionalProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListProfessionalAgendaAction
{
    public function __invoke(
        ProfessionalProfile $professionalProfile,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?string $status = null,
        ?int $serviceId = null,
    ): array {
        $baseQuery = Booking::query()
            ->where('professional_id', $professionalProfile->id);

        $visibleQuery = $this->applyFilters(
            query: clone $baseQuery,
            status: $status,
            serviceId: $serviceId,
        );

        /** @var Collection<int, Booking> $bookings */
        $bookings = (clone $visibleQuery)
            ->whereBetween('starts_at', [$from, $to])
            ->with([
                'client:id,name,avatar_url',
                'service:id,name,duration_minutes,modality,address',
                'payment:id,booking_id,status,amount,currency',
                'videoSession:id,booking_id,status,scheduled_start_at,scheduled_end_at,started_at,ended_at,cancelled_at',
                'clientPackage:id,package_product_id,client_id,professional_id,service_id,status,total_sessions,used_sessions,price_snapshot,currency,purchased_at,expires_at',
                'packageSession:id,client_package_id,booking_id,client_id,professional_id,status,consumed_at,released_at',
            ])
            ->orderBy('starts_at')
            ->get();

        return [
            'bookings' => $bookings,
            'range_summary' => $this->buildSummary(
                (clone $visibleQuery)->whereBetween('starts_at', [$from, $to])
            ),
            'global_summary' => $this->buildSummary(clone $baseQuery),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildSummary(Builder $query): array
    {
        $summary = [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'paid' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'no_show' => 0,
        ];

        $counts = $query
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $summary['total'] = (int) $counts->sum();

        foreach ($counts as $status => $count) {
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) $count;
            }
        }

        return $summary;
    }

    private function applyFilters(
        Builder $query,
        ?string $status,
        ?int $serviceId,
    ): Builder {
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($serviceId !== null) {
            $query->where('service_id', $serviceId);
        }

        return $query;
    }
}
