<?php

namespace App\Services\Payment;

use App\DTOs\Payment\ProviderWebhookData;
use App\Enums\Payment\PaymentWebhookEventStatus;
use App\Models\Payment\PaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PaymentWebhookIdempotencyService
{
    /**
     * @return array{0: PaymentWebhookEvent, 1: bool}
     */
    public function acquire(ProviderWebhookData $webhook): array
    {
        $idempotencyKey = $this->keyFor($webhook);
        $attributes = ['idempotency_key' => $idempotencyKey];
        $values = [
            'provider' => $webhook->provider,
            'provider_event_id' => $webhook->providerEventId,
            'event_type' => $webhook->eventType,
            'resource_type' => $webhook->resourceType,
            'resource_id' => $webhook->resourceId,
            'signature_valid' => $webhook->signatureValid,
            'status' => PaymentWebhookEventStatus::Received,
            'payload' => $webhook->payload,
        ];

        try {
            $event = PaymentWebhookEvent::query()->firstOrCreate($attributes, $values);
        } catch (QueryException) {
            $event = PaymentWebhookEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();
        }

        if (
            ! $event->wasRecentlyCreated
            && $webhook->signatureValid
            && ! $event->signature_valid
            && $event->status === PaymentWebhookEventStatus::InvalidSignature
        ) {
            $reacquired = DB::transaction(function () use (
                $event,
                $values
            ): ?PaymentWebhookEvent {
                $lockedEvent = PaymentWebhookEvent::query()
                    ->whereKey($event->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $lockedEvent->signature_valid
                    || $lockedEvent->status
                        !== PaymentWebhookEventStatus::InvalidSignature
                ) {
                    return null;
                }

                $lockedEvent->update([
                    ...$values,
                    'failure_reason' => null,
                    'processed_at' => null,
                ]);

                return $lockedEvent->refresh();
            });

            if ($reacquired) {
                return [$reacquired, true];
            }
        }

        return [$event, $event->wasRecentlyCreated];
    }

    private function keyFor(ProviderWebhookData $webhook): string
    {
        $source = $webhook->provider->value.'|';

        if ($webhook->providerEventId) {
            $source .= 'event|'.$webhook->providerEventId;
        } else {
            $source .= implode('|', [
                'resource',
                $webhook->eventType ?? '',
                $webhook->resourceType ?? '',
                $webhook->resourceId ?? '',
                json_encode($webhook->payload, JSON_THROW_ON_ERROR),
            ]);
        }

        return hash('sha256', $source);
    }
}
