<?php

namespace App\Listeners\ActivityLog;

use App\Events\Booking\BookingCancelled;
use App\Events\Booking\BookingConfirmed;
use App\Events\Booking\BookingCreated;
use App\Events\Booking\BookingRescheduled;
use App\Events\Notification\NotificationCreated;
use App\Events\Package\PackagePurchased;
use App\Events\Package\PackageSessionReserved;
use App\Events\Payment\PaymentFailed;
use App\Events\Payment\PaymentSucceeded;
use App\Events\Video\VideoSessionCreated;
use App\Events\Video\VideoSessionEnded;
use App\Events\Video\VideoSessionJoined;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\Video\VideoSession;
use App\Models\Video\VideoSessionParticipant;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

final readonly class LogDomainActivity
{
    public function __construct(
        private ActivityLogger $activityLogger,
    ) {}

    public function handle(object $event): void
    {
        match (true) {
            $event instanceof BookingCreated => $this->bookingCreated($event),
            $event instanceof BookingConfirmed => $this->bookingConfirmed($event),
            $event instanceof BookingCancelled => $this->bookingCancelled($event),
            $event instanceof BookingRescheduled => $this->bookingRescheduled($event),
            $event instanceof PaymentSucceeded => $this->paymentSucceeded($event),
            $event instanceof PaymentFailed => $this->paymentFailed($event),
            $event instanceof PackagePurchased => $this->packagePurchased($event),
            $event instanceof PackageSessionReserved => $this->packageSessionReserved($event),
            $event instanceof VideoSessionCreated => $this->videoSessionCreated($event),
            $event instanceof VideoSessionJoined => $this->videoSessionJoined($event),
            $event instanceof VideoSessionEnded => $this->videoSessionEnded($event),
            $event instanceof NotificationCreated => $this->notificationCreated($event),
            default => null,
        };
    }

    private function bookingCreated(BookingCreated $event): void
    {
        $booking = $event->booking;

        $this->activityLogger->record(
            event: ActivityLogEvent::BookingCreated,
            entityType: 'booking',
            entityId: $booking->id,
            entityOwnerId: $booking->professional_id,
            metadata: $this->bookingMetadata($booking),
            actor: $booking->client,
            actingAs: ActivityLogActorMode::Client,
        );
    }

    private function bookingConfirmed(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        $this->activityLogger->record(
            event: ActivityLogEvent::BookingConfirmed,
            entityType: 'booking',
            entityId: $booking->id,
            entityOwnerId: $booking->professional_id,
            metadata: [
                ...$this->bookingMetadata($booking),
                'previous_status' => 'pending',
                'new_status' => $booking->status,
            ],
            actor: $booking->professional?->user,
            actingAs: ActivityLogActorMode::Professional,
        );
    }

    private function bookingCancelled(BookingCancelled $event): void
    {
        $booking = $event->booking;

        $this->activityLogger->record(
            event: ActivityLogEvent::BookingCancelled,
            entityType: 'booking',
            entityId: $booking->id,
            entityOwnerId: $booking->professional_id,
            metadata: [
                ...$this->bookingMetadata($booking),
                'cancelled_by' => $event->actor?->id,
                'previous_status' => $event->previousStatus,
                'new_status' => $booking->status,
                'reason' => $booking->cancellation_reason,
                'cancelled_at' => $booking->cancelled_at,
            ],
            actor: $event->actor,
            actingAs: $event->actor?->id === $booking->client_id
                ? ActivityLogActorMode::Client
                : ActivityLogActorMode::Professional,
        );
    }

    private function bookingRescheduled(BookingRescheduled $event): void
    {
        $booking = $event->booking;

        $this->activityLogger->record(
            event: ActivityLogEvent::BookingRescheduled,
            entityType: 'booking',
            entityId: $booking->id,
            entityOwnerId: $booking->professional_id,
            metadata: [
                ...$this->bookingMetadata($booking),
                'old_starts_at' => $event->oldStartsAt,
                'old_ends_at' => $event->oldEndsAt,
                'new_starts_at' => $booking->starts_at,
                'new_ends_at' => $booking->ends_at,
                'reason' => $booking->reschedule_reason,
            ],
            actor: $event->actor,
            actingAs: $event->actor?->id === $booking->client_id
                ? ActivityLogActorMode::Client
                : ActivityLogActorMode::Professional,
        );
    }

    private function paymentSucceeded(PaymentSucceeded $event): void
    {
        $payment = $event->payment;
        $metadata = [
            'payment_id' => $payment->id,
            'payment_intent_id' => $payment->payment_intent_id,
            'client_id' => $payment->client_id,
            'professional_id' => $payment->professional_id,
            'booking_id' => $payment->booking_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'new_status' => $payment->status,
            'paid_at' => $payment->paid_at,
        ];

        $this->activityLogger->record(
            event: ActivityLogEvent::PaymentApproved,
            entityType: 'payment',
            entityId: $payment->id,
            entityOwnerId: $payment->professional_id,
            metadata: $metadata,
            actor: $payment->client,
            actingAs: ActivityLogActorMode::Client,
        );

        $this->activityLogger->record(
            event: ActivityLogEvent::BookingPaid,
            entityType: 'booking',
            entityId: $payment->booking_id,
            entityOwnerId: $payment->professional_id,
            metadata: $metadata,
            actor: $payment->client,
            actingAs: ActivityLogActorMode::Client,
        );
    }

    private function paymentFailed(PaymentFailed $event): void
    {
        $intent = $event->paymentIntent;

        $this->activityLogger->record(
            event: ActivityLogEvent::PaymentRejected,
            entityType: 'payment_intent',
            entityId: $intent->id,
            entityOwnerId: $intent->professional_id,
            severity: 'warning',
            metadata: [
                'payment_intent_id' => $intent->id,
                'client_id' => $intent->client_id,
                'professional_id' => $intent->professional_id,
                'booking_id' => $intent->booking_id,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'provider' => $intent->provider,
                'new_status' => $intent->status,
                'failure_reason' => $intent->failure_reason,
            ],
            actor: $intent->client,
            actingAs: ActivityLogActorMode::Client,
        );
    }

    private function packagePurchased(PackagePurchased $event): void
    {
        $clientPackage = ClientPackage::query()
            ->with(['packageProduct', 'client'])
            ->find($event->clientPackageId);

        if (! $clientPackage) {
            return;
        }

        $this->activityLogger->record(
            event: ActivityLogEvent::PackagePurchased,
            entityType: 'client_package',
            entityId: $clientPackage->id,
            entityOwnerId: $clientPackage->professional_id,
            metadata: [
                'client_package_id' => $clientPackage->id,
                'package_product_id' => $clientPackage->package_product_id,
                'client_id' => $clientPackage->client_id,
                'professional_id' => $clientPackage->professional_id,
                'service_id' => $clientPackage->service_id,
                'sessions_total' => $clientPackage->total_sessions,
                'sessions_remaining' => $clientPackage->remainingSessions(),
                'amount_paid' => $clientPackage->price_snapshot,
                'currency' => $clientPackage->currency,
            ],
            actor: $clientPackage->client,
            actingAs: ActivityLogActorMode::Client,
        );
    }

    private function packageSessionReserved(PackageSessionReserved $event): void
    {
        $session = PackageSession::query()
            ->with(['clientPackage', 'client'])
            ->find($event->packageSessionId);

        if (! $session) {
            return;
        }

        $this->activityLogger->record(
            event: ActivityLogEvent::PackageSessionReserved,
            entityType: 'package_session',
            entityId: $session->id,
            entityOwnerId: $session->professional_id,
            metadata: [
                'package_session_id' => $session->id,
                'client_package_id' => $session->client_package_id,
                'booking_id' => $session->booking_id,
                'client_id' => $session->client_id,
                'professional_id' => $session->professional_id,
                'status' => $session->status,
                'sessions_remaining' => $session->clientPackage?->remainingSessions(),
            ],
            actor: $session->client,
            actingAs: ActivityLogActorMode::Client,
        );
    }

    private function videoSessionCreated(VideoSessionCreated $event): void
    {
        $videoSession = VideoSession::query()
            ->with('professional.user')
            ->find($event->videoSessionId);

        if (! $videoSession) {
            return;
        }

        $this->activityLogger->record(
            event: ActivityLogEvent::VideoSessionCreated,
            entityType: 'video_session',
            entityId: $videoSession->id,
            entityOwnerId: $videoSession->professional_id,
            metadata: $this->videoSessionMetadata($videoSession),
            actor: $videoSession->professional?->user,
            actingAs: ActivityLogActorMode::Professional,
        );
    }

    private function videoSessionJoined(VideoSessionJoined $event): void
    {
        $videoSession = VideoSession::query()->find($event->videoSessionId);
        $participant = VideoSessionParticipant::query()->find($event->participantId);

        if (! $videoSession || ! $participant) {
            return;
        }

        $this->activityLogger->record(
            event: ActivityLogEvent::VideoSessionJoined,
            entityType: 'video_session',
            entityId: $videoSession->id,
            entityOwnerId: $videoSession->professional_id,
            metadata: [
                ...$this->videoSessionMetadata($videoSession),
                'participant_user_id' => $participant->user_id,
                'participant_role' => $participant->role,
                'join_count' => $participant->join_count,
            ],
            actor: $participant->user,
            actingAs: $participant->role === ActivityLogActorMode::Professional->value
                ? ActivityLogActorMode::Professional
                : ActivityLogActorMode::Client,
        );
    }

    private function videoSessionEnded(VideoSessionEnded $event): void
    {
        $videoSession = VideoSession::query()
            ->with('professional.user')
            ->find($event->videoSessionId);

        if (! $videoSession) {
            return;
        }

        $this->activityLogger->record(
            event: ActivityLogEvent::VideoSessionClosed,
            entityType: 'video_session',
            entityId: $videoSession->id,
            entityOwnerId: $videoSession->professional_id,
            metadata: $this->videoSessionMetadata($videoSession),
            actor: $videoSession->professional?->user,
            actingAs: ActivityLogActorMode::Professional,
        );
    }

    private function notificationCreated(NotificationCreated $event): void
    {
        $notification = $event->notification;

        $this->activityLogger->record(
            event: ActivityLogEvent::NotificationCreated,
            entityType: 'notification',
            entityId: $notification->id,
            entityOwnerId: $notification->recipient_id,
            metadata: [
                'notification_id' => $notification->id,
                'recipient_id' => $notification->recipient_id,
                'type' => $notification->type,
                'channel' => 'database',
            ],
            actingAs: ActivityLogActorMode::System,
        );
    }

    private function bookingMetadata(object $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'professional_id' => $booking->professional_id,
            'service_id' => $booking->service_id,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'status' => $booking->status,
            'modality' => $booking->modality,
            'price_snapshot' => $booking->price_snapshot,
            'used_package' => $booking->client_package_id !== null,
            'client_package_id' => $booking->client_package_id,
        ];
    }

    private function videoSessionMetadata(VideoSession $videoSession): array
    {
        return [
            'video_session_id' => $videoSession->id,
            'booking_id' => $videoSession->booking_id,
            'room_name' => $videoSession->room_name,
            'provider' => $videoSession->provider,
            'status' => $videoSession->status,
        ];
    }
}
