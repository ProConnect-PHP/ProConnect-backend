<?php

namespace App\Actions\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\User\ProfessionalProfile;
use Carbon\CarbonImmutable;
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
        $query = Booking::query()
            ->with([
                'client:id,name,avatar_url',
                'service:id,name,duration_minutes,modality,address',
                'payment:id,booking_id,status,amount,currency',
                'videoSession:id,booking_id,status,scheduled_start_at,scheduled_end_at,started_at,ended_at,cancelled_at',
                'clientPackage:id,package_product_id,client_id,professional_id,service_id,status,total_sessions,used_sessions,price_snapshot,currency,purchased_at,expires_at',
                'packageSession:id,client_package_id,booking_id,client_id,professional_id,status,consumed_at,released_at',
            ])
            ->where('professional_id', $professionalProfile->id)
            ->where(function ($query) use ($from, $to): void {
                $query
                    ->where('starts_at', '<', $to)
                    ->where('ends_at', '>', $from);
            })
            ->orderBy('starts_at');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($serviceId !== null) {
            $query->where('service_id', $serviceId);
        }

        /** @var Collection<int, Booking> $bookings */
        $bookings = $query->get();

        return [
            'bookings' => $bookings,
            'summary' => $this->summary($bookings),
        ];
    }

    /**
     * @param Collection<int, Booking> $bookings
     * @return array<string, int>
     */
    private function summary(Collection $bookings): array
    {
        $summary = [
            'total' => $bookings->count(),
            'pending' => 0,
            'confirmed' => 0,
            'paid' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'no_show' => 0,
        ];

        foreach ($bookings as $booking) {
            $status = $booking->status instanceof BookingStatus
                ? $booking->status->value
                : (string) $booking->status;

            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            }
        }

        return $summary;
    }
}
