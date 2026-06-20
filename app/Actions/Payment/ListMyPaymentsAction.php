<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\PaymentHistoryItemData;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Services\Payment\BookingPaymentEligibility;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ListMyPaymentsAction
{
    public function __construct(
        private readonly BookingPaymentEligibility $bookingPaymentEligibility,
    ) {}

    public function __invoke(
        User $client,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        $payments = $this->paymentsFor($client);
        $succeededPayables = $this->succeededPayablesFor($client);

        $paymentItems = $payments
            ->map(fn (Payment $payment): PaymentHistoryItemData => $this->paymentItem($payment))
            ->values()
            ->toBase();

        $intentItems = $this->relevantIntentsFor($client)
            ->map(fn (PaymentIntent $intent): PaymentHistoryItemData => $this->intentItem(
                $intent,
                $succeededPayables,
            ))
            ->values()
            ->toBase();

        $items = $paymentItems
            ->merge($intentItems)
            ->sort(function (PaymentHistoryItemData $left, PaymentHistoryItemData $right): int {
                $dateComparison = ($right->createdAt?->getTimestamp() ?? 0)
                    <=> ($left->createdAt?->getTimestamp() ?? 0);

                return $dateComparison !== 0
                    ? $dateComparison
                    : strcmp($right->id, $left->id);
            })
            ->values();

        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    public function find(User $client, string $historyId): ?PaymentHistoryItemData
    {
        [$source, $id] = array_pad(explode(':', $historyId, 2), 2, null);

        if (! is_string($id) || $id === '') {
            return null;
        }

        if ($source === 'payment') {
            $payment = Payment::query()
                ->with(['booking', 'packageProduct'])
                ->where('client_id', $client->id)
                ->whereIn('status', $this->realPaymentStatuses())
                ->find($id);

            return $payment ? $this->paymentItem($payment) : null;
        }

        if ($source !== 'intent') {
            return null;
        }

        $intent = PaymentIntent::query()
            ->with(['booking', 'packageProduct'])
            ->where('client_id', $client->id)
            ->whereDoesntHave('payment')
            ->whereNotNull('provider')
            ->whereIn('status', $this->relevantIntentStatuses())
            ->find($id);

        if (! $intent || ! $this->hasProviderEvidence($intent)) {
            return null;
        }

        return $this->intentItem($intent, $this->succeededPayablesFor($client));
    }

    /** @return Collection<int, PaymentIntent> */
    public function relatedAttemptsFor(
        User $client,
        PaymentHistoryItemData $item,
    ): Collection {
        $query = PaymentIntent::query()
            ->where('client_id', $client->id)
            ->whereNotNull('provider');

        if ($item->bookingId !== null) {
            $query->where('booking_id', $item->bookingId);
        } elseif ($item->packageProductId !== null) {
            $query->where('package_product_id', $item->packageProductId);
        } else {
            return collect();
        }

        return $query
            ->latest('created_at')
            ->get()
            ->filter(fn (PaymentIntent $intent): bool => $this->hasProviderEvidence($intent))
            ->values();
    }

    /** @return Collection<int, Payment> */
    private function paymentsFor(User $client): Collection
    {
        return Payment::query()
            ->with(['booking', 'packageProduct', 'clientPackage'])
            ->where('client_id', $client->id)
            ->whereIn('status', $this->realPaymentStatuses())
            ->get();
    }

    /** @return Collection<int, PaymentIntent> */
    private function relevantIntentsFor(User $client): Collection
    {
        return PaymentIntent::query()
            ->with(['booking', 'packageProduct'])
            ->where('client_id', $client->id)
            ->whereDoesntHave('payment')
            ->whereNotNull('provider')
            ->whereIn('status', $this->relevantIntentStatuses())
            ->get()
            ->filter(fn (PaymentIntent $intent): bool => $this->hasProviderEvidence($intent))
            ->values();
    }

    /** @return array{booking: array<string, true>, package: array<string, true>} */
    private function succeededPayablesFor(User $client): array
    {
        $payments = Payment::query()
            ->where('client_id', $client->id)
            ->where('status', PaymentStatus::Succeeded->value)
            ->get(['booking_id', 'package_product_id']);

        return [
            'booking' => $payments
                ->pluck('booking_id')
                ->filter()
                ->mapWithKeys(fn (string $id): array => [$id => true])
                ->all(),
            'package' => $payments
                ->pluck('package_product_id')
                ->filter()
                ->mapWithKeys(fn (string $id): array => [$id => true])
                ->all(),
        ];
    }

    /** @param array{booking: array<string, true>, package: array<string, true>} $succeededPayables */
    private function intentItem(
        PaymentIntent $intent,
        array $succeededPayables,
    ): PaymentHistoryItemData {
        $status = $this->normalizedIntentStatus($intent);

        return new PaymentHistoryItemData(
            id: 'intent:'.$intent->id,
            source: 'payment_intent',
            paymentId: null,
            paymentIntentId: $intent->id,
            bookingId: $intent->booking_id,
            packageProductId: $intent->package_product_id,
            provider: $intent->provider?->value ?? $intent->provider,
            status: $status,
            displayStatus: $this->displayStatus($status),
            amount: $intent->amount,
            currency: $intent->currency,
            providerReference: $this->intentProviderReference($intent),
            providerPaymentId: null,
            paidAt: null,
            failedAt: $intent->failed_at,
            createdAt: $intent->created_at,
            failureReason: $intent->failure_reason
                ?? ($status === PaymentIntentStatus::CheckoutCreated->value
                    ? 'No encontramos un pago asociado a este intento.'
                    : null),
            canRetry: $this->canRetryIntent($intent, $status, $succeededPayables),
            booking: $this->bookingData($intent->booking),
            packageProduct: $this->packageProductData($intent->packageProduct),
        );
    }

    private function paymentItem(Payment $payment): PaymentHistoryItemData
    {
        $status = $payment->status?->value ?? $payment->status;

        return new PaymentHistoryItemData(
            id: 'payment:'.$payment->id,
            source: 'payment',
            paymentId: $payment->id,
            paymentIntentId: $payment->payment_intent_id,
            bookingId: $payment->booking_id,
            packageProductId: $payment->package_product_id,
            provider: $payment->provider?->value ?? $payment->provider,
            status: $status,
            displayStatus: $this->displayStatus($status),
            amount: $payment->amount,
            currency: $payment->currency,
            providerReference: $payment->provider_reference,
            providerPaymentId: $payment->provider_payment_id,
            paidAt: $payment->paid_at,
            failedAt: $payment->failed_at,
            createdAt: $payment->created_at,
            failureReason: $payment->failure_reason,
            canRetry: false,
            booking: $this->bookingData($payment->booking),
            packageProduct: $this->packageProductData($payment->packageProduct),
        );
    }

    /** @param array{booking: array<string, true>, package: array<string, true>} $succeededPayables */
    private function canRetryIntent(
        PaymentIntent $intent,
        string $status,
        array $succeededPayables,
    ): bool {
        if (! in_array($status, [
            PaymentIntentStatus::Failed->value,
            PaymentIntentStatus::Rejected->value,
            PaymentIntentStatus::Expired->value,
            PaymentIntentStatus::Cancelled->value,
            PaymentIntentStatus::CheckoutCreated->value,
        ], true)) {
            return false;
        }

        if ($intent->booking !== null
            && ! $this->bookingPaymentEligibility->canStartOrContinuePayment($intent->booking)) {
            return false;
        }

        if ($intent->booking_id !== null
            && isset($succeededPayables['booking'][$intent->booking_id])) {
            return false;
        }

        return $intent->package_product_id === null
            || ! isset($succeededPayables['package'][$intent->package_product_id]);
    }

    private function normalizedIntentStatus(PaymentIntent $intent): string
    {
        $status = $intent->status?->value ?? $intent->status;
        $providerStatus = strtolower((string) data_get(
            $intent->metadata,
            'raw_provider_status',
            ''
        ));

        return $status === PaymentIntentStatus::Failed->value
            && in_array($providerStatus, ['rejected', 'denied', 'declined'], true)
            ? PaymentIntentStatus::Rejected->value
            : $status;
    }

    private function hasProviderEvidence(PaymentIntent $intent): bool
    {
        if (filled($intent->provider_reference) || filled($intent->checkout_url)) {
            return true;
        }

        $metadata = is_array($intent->metadata) ? $intent->metadata : [];

        return collect([
            data_get($metadata, 'raw_provider_status'),
            data_get($metadata, 'provider_payment_id'),
            data_get($metadata, 'external_status'),
            data_get($metadata, 'paypal_order_id'),
            data_get($metadata, 'order_id'),
        ])->contains(fn (mixed $value): bool => filled($value));
    }

    private function intentProviderReference(PaymentIntent $intent): ?string
    {
        $metadata = is_array($intent->metadata) ? $intent->metadata : [];
        $reference = $intent->provider_reference
            ?? data_get($metadata, 'paypal_order_id')
            ?? data_get($metadata, 'order_id');

        return is_scalar($reference) && filled($reference)
            ? (string) $reference
            : null;
    }

    /** @return array<int, string> */
    private function realPaymentStatuses(): array
    {
        return [
            PaymentStatus::Succeeded->value,
            PaymentStatus::Cancelled->value,
        ];
    }

    /** @return array<int, string> */
    private function relevantIntentStatuses(): array
    {
        return [
            PaymentIntentStatus::CheckoutCreated->value,
            PaymentIntentStatus::Processing->value,
            PaymentIntentStatus::Failed->value,
            PaymentIntentStatus::Rejected->value,
            PaymentIntentStatus::Cancelled->value,
            PaymentIntentStatus::Expired->value,
        ];
    }

    private function displayStatus(string $status): string
    {
        return match ($status) {
            PaymentStatus::Succeeded->value => 'paid',
            PaymentStatus::Cancelled->value,
            PaymentIntentStatus::Cancelled->value => 'cancelled',
            PaymentIntentStatus::Rejected->value => 'rejected',
            PaymentIntentStatus::Failed->value => 'failed',
            PaymentIntentStatus::Expired->value => 'expired',
            PaymentIntentStatus::Processing->value => 'processing',
            PaymentIntentStatus::CheckoutCreated->value => 'not_confirmed',
            default => $status,
        };
    }

    /** @return array<string, mixed>|null */
    private function bookingData(mixed $booking): ?array
    {
        if (! $booking) {
            return null;
        }

        return [
            'id' => $booking->id,
            'status' => $booking->status?->value ?? $booking->status,
            'starts_at' => $booking->starts_at?->toDateTimeString(),
            'ends_at' => $booking->ends_at?->toDateTimeString(),
            'service_id' => $booking->service_id,
        ];
    }

    /** @return array<string, mixed>|null */
    private function packageProductData(mixed $packageProduct): ?array
    {
        if (! $packageProduct) {
            return null;
        }

        return [
            'id' => $packageProduct->id,
            'name' => $packageProduct->name,
            'sessions_count' => $packageProduct->sessions_count,
            'service_id' => $packageProduct->service_id,
        ];
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
