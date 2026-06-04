<?php

namespace App\Actions\Package;

use App\Enums\Package\ClientPackageStatus;
use App\Enums\Package\PackageSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;

class ReleasePackageSessionAction
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

        $clientPackage = ClientPackage::query()
            ->whereKey($session->client_package_id)
            ->lockForUpdate()
            ->first();

        if (! $clientPackage) {
            return;
        }

        $session->update([
            'status' => PackageSessionStatus::Released,
            'released_at' => now(),
        ]);

        $clientPackage->update([
            'used_sessions' => max(0, $clientPackage->used_sessions - 1),
        ]);

        $clientPackage->refresh();

        if ($clientPackage->status === ClientPackageStatus::Depleted) {
            $isExpired = $clientPackage->expires_at !== null && $clientPackage->expires_at->isPast();

            $clientPackage->update([
                'status' => $isExpired
                    ? ClientPackageStatus::Expired
                    : ClientPackageStatus::Active,
                'depleted_at' => null,
            ]);
        }
    }
}
