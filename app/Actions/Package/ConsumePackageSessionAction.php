<?php

namespace App\Actions\Package;

use App\Enums\Package\PackageSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Package\PackageSession;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class ConsumePackageSessionAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

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

        $session->load('clientPackage');

        $this->activityLogger->record(
            event: ActivityLogEvent::PackageSessionConsumed,
            entityType: 'package_session',
            entityId: $session->id,
            entityOwnerId: $session->professional_id,
            metadata: [
                'package_session_id' => $session->id,
                'client_package_id' => $session->client_package_id,
                'booking_id' => $booking->id,
                'sessions_remaining' => $session->clientPackage?->remainingSessions(),
                'consumed_at' => $session->consumed_at,
            ],
            actingAs: ActivityLogActorMode::Client,
        );
    }
}
