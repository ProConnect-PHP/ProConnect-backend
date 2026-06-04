<?php

namespace App\Actions\Package;

use App\Enums\Package\PackageSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Package\PackageSession;

class ConsumePackageSessionAction
{
    public function __invoke(Booking $booking): void
    {
        $session = PackageSession::query()
            ->where('booking_id', $booking->id)
            ->lockForUpdate()
            ->first();

        if (! $session || $session->status !== PackageSessionStatus::Reserved) {
            return;
        }

        $session->update([
            'status' => PackageSessionStatus::Consumed,
            'consumed_at' => now(),
        ]);
    }
}
