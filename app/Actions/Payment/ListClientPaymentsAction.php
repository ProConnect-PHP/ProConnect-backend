<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\ClientPaymentMovementData;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ListClientPaymentsAction
{
    /**
     * The history is intentionally normalized in PHP rather than with a SQL
     * UNION: payments and intents have different lifecycle columns, and a
     * client's payment history is a bounded, user-scoped dataset. The action
     * still queries each source with its relationships eagerly loaded.
     *
     * @param  array<string, mixed>  $filters
     */
    public function __invoke(
        User $client,
        int $perPage = 10,
        int $page = 1,
        array $filters = [],
    ): LengthAwarePaginator {
        $kind = $filters['kind'] ?? 'all';
        $movements = collect();

        if ($kind !== 'payment_intent') {
            $movements = $movements->merge(
                Payment::query()
                    ->with(['booking', 'packageProduct', 'clientPackage'])
                    ->where('client_id', $client->id)
                    ->get()
                    ->map(fn (Payment $payment): ClientPaymentMovementData => $this->paymentMovement($payment))
            );
        }

        if ($kind !== 'payment') {
            $movements = $movements->merge(
                PaymentIntent::query()
                    ->with(['booking', 'packageProduct'])
                    ->where('client_id', $client->id)
                    // A concrete Payment is the authoritative, final row.
                    ->doesntHave('payment')
                    ->get()
                    ->map(fn (PaymentIntent $intent): ClientPaymentMovementData => $this->intentMovement($intent))
            );
        }

        $movements = $this->filter($movements, $filters)
            ->sort(function (
                ClientPaymentMovementData $left,
                ClientPaymentMovementData $right
            ): int {
                $dateComparison = ($right->relevantAt?->getTimestamp() ?? 0)
                    <=> ($left->relevantAt?->getTimestamp() ?? 0);

                return $dateComparison !== 0
                    ? $dateComparison
                    : strcmp($right->id, $left->id);
            })
            ->values();

        $perPage = min(max($perPage, 1), 50);
        $page = max($page, 1);

        return new LengthAwarePaginator(
            $movements->forPage($page, $perPage)->values(),
            $movements->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    private function paymentMovement(Payment $payment): ClientPaymentMovementData
    {
        $status = $payment->status?->value ?? (string) $payment->status;
        $isSuccessful = in_array($status, ['succeeded', 'approved'], true);
        $isFinal = in_array($status, [
            'succeeded',
            'approved',
            'rejected',
            'failed',
            'cancelled',
            'refunded',
            'partially_refunded',
        ], true);

        return new ClientPaymentMovementData(
            id: (string) $payment->id,
            kind: 'payment',
            status: $status,
            displayStatus: $this->displayStatus($status),
            isFinal: $isFinal,
            isSuccessful: $isSuccessful,
            isPending: ! $isFinal,
            canRetry: in_array($status, ['rejected', 'failed', 'cancelled'], true),
            canContinueCheckout: false,
            canViewBooking: $payment->booking_id !== null,
            canRefreshStatus: ! $isFinal,
            amount: $payment->amount,
            currency: $payment->currency,
            provider: $payment->provider?->value ?? (string) $payment->provider,
            providerReference: $payment->provider_reference,
            providerPaymentId: $payment->provider_payment_id,
            providerStatus: $payment->raw_provider_status,
            paymentIntentId: $payment->payment_intent_id,
            bookingId: $payment->booking_id,
            packageProductId: $payment->package_product_id,
            clientPackageId: $payment->client_package_id,
            clientId: $payment->client_id,
            professionalId: $payment->professional_id,
            checkoutUrl: null,
            booking: $this->bookingData($payment->booking),
            packageProduct: $this->packageProductData($payment->packageProduct),
            clientPackage: $this->clientPackageData($payment->clientPackage),
            metadata: $payment->metadata,
            failureReason: $payment->failure_reason,
            createdAt: $payment->created_at,
            updatedAt: $payment->updated_at,
            relevantAt: $payment->paid_at ?? $payment->created_at,
            paidAt: $payment->paid_at,
            failedAt: $payment->failed_at,
            cancelledAt: null,
            expiresAt: null,
            refundedAt: $payment->refunded_at,
        );
    }

    private function intentMovement(
        PaymentIntent $intent
    ): ClientPaymentMovementData {
        $status = $intent->status?->value ?? (string) $intent->status;
        $isSuccessful = $status === 'succeeded';
        $isFinal = in_array($status, [
            'succeeded',
            'failed',
            'cancelled',
            'expired',
        ], true);
        $metadata = $intent->metadata ?? [];
        $providerStatus = data_get($metadata, 'paypal_order_status')
            ?? data_get($metadata, 'raw_provider_status')
            ?? data_get($metadata, 'external_status');
        $providerReference = $intent->provider_reference
            ?? data_get($metadata, 'paypal_order_id')
            ?? data_get($metadata, 'order_id');

        return new ClientPaymentMovementData(
            id: (string) $intent->id,
            kind: 'payment_intent',
            status: $status,
            displayStatus: $this->displayStatus($status, $providerStatus),
            isFinal: $isFinal,
            isSuccessful: $isSuccessful,
            isPending: ! $isFinal,
            canRetry: in_array($status, ['failed', 'cancelled', 'expired'], true),
            canContinueCheckout: $status === 'checkout_created'
                && is_string($intent->checkout_url)
                && $intent->checkout_url !== '',
            canViewBooking: $intent->booking_id !== null,
            canRefreshStatus: ! $isFinal,
            amount: $intent->amount,
            currency: $intent->currency,
            provider: $intent->provider?->value ?? (string) $intent->provider,
            providerReference: is_scalar($providerReference)
                ? (string) $providerReference
                : null,
            providerPaymentId: null,
            providerStatus: is_scalar($providerStatus)
                ? (string) $providerStatus
                : null,
            paymentIntentId: (string) $intent->id,
            bookingId: $intent->booking_id,
            packageProductId: $intent->package_product_id,
            clientPackageId: null,
            clientId: $intent->client_id,
            professionalId: $intent->professional_id,
            checkoutUrl: $intent->checkout_url,
            booking: $this->bookingData($intent->booking),
            packageProduct: $this->packageProductData($intent->packageProduct),
            clientPackage: null,
            metadata: $metadata,
            failureReason: $intent->failure_reason,
            createdAt: $intent->created_at,
            updatedAt: $intent->updated_at,
            relevantAt: $intent->updated_at ?? $intent->created_at,
            paidAt: $intent->succeeded_at,
            failedAt: $intent->failed_at,
            cancelledAt: $intent->cancelled_at,
            expiresAt: $intent->expires_at,
            refundedAt: null,
        );
    }

    /**
     * @param  Collection<int, ClientPaymentMovementData>  $movements
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ClientPaymentMovementData>
     */
    private function filter(Collection $movements, array $filters): Collection
    {
        $statuses = $this->filterValues($filters['status'] ?? null);
        $provider = strtolower(trim((string) ($filters['provider'] ?? '')));
        $bookingId = $filters['booking_id'] ?? null;
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $dateFrom = isset($filters['date_from'])
            ? CarbonImmutable::parse($filters['date_from'])->startOfDay()
            : null;
        $dateTo = isset($filters['date_to'])
            ? CarbonImmutable::parse($filters['date_to'])->endOfDay()
            : null;
        $onlyPending = (bool) ($filters['only_pending'] ?? false);
        $onlyFinal = (bool) ($filters['only_final'] ?? false);

        return $movements->filter(function (
            ClientPaymentMovementData $movement
        ) use (
            $statuses,
            $provider,
            $bookingId,
            $search,
            $dateFrom,
            $dateTo,
            $onlyPending,
            $onlyFinal
        ): bool {
            if ($statuses !== [] && ! $this->matchesStatus($movement, $statuses)) {
                return false;
            }

            if ($provider !== '' && strtolower($movement->provider) !== $provider) {
                return false;
            }

            if ($bookingId !== null && $movement->bookingId !== $bookingId) {
                return false;
            }

            if ($onlyPending && ! $movement->isPending) {
                return false;
            }

            if ($onlyFinal && ! $movement->isFinal) {
                return false;
            }

            if ($dateFrom && (! $movement->relevantAt || $movement->relevantAt->lt($dateFrom))) {
                return false;
            }

            if ($dateTo && (! $movement->relevantAt || $movement->relevantAt->gt($dateTo))) {
                return false;
            }

            return $search === '' || $this->matchesSearch($movement, $search);
        })->values();
    }

    /** @return array<int, string> */
    private function filterValues(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $status): string => strtolower(trim($status)),
            explode(',', $value)
        )));
    }

    /** @param array<int, string> $statuses */
    private function matchesStatus(
        ClientPaymentMovementData $movement,
        array $statuses
    ): bool {
        foreach ($statuses as $status) {
            if (in_array($status, [strtolower($movement->status), strtolower((string) $movement->providerStatus)], true)) {
                return true;
            }

            if (in_array($status, ['paid', 'completed'], true) && $movement->isSuccessful) {
                return true;
            }

            if ($status === 'denied' && in_array($movement->status, ['failed', 'rejected'], true)) {
                return true;
            }

            if ($status === 'pending_capture' && $movement->status === 'processing') {
                return true;
            }
        }

        return false;
    }

    private function matchesSearch(
        ClientPaymentMovementData $movement,
        string $search
    ): bool {
        return collect([
            $movement->id,
            $movement->paymentIntentId,
            $movement->providerReference,
            $movement->providerPaymentId,
            $movement->providerStatus,
        ])->filter()
            ->contains(fn (string $value): bool => str_contains(
                strtolower($value),
                $search
            ));
    }

    private function displayStatus(
        string $status,
        mixed $providerStatus = null
    ): string {
        return match (strtolower($status)) {
            'succeeded', 'approved', 'paid', 'completed' => 'Pagado',
            'pending' => 'Pendiente',
            'checkout_created' => 'Checkout creado',
            'processing', 'pending_capture' => 'Procesando',
            'failed', 'rejected', 'denied' => 'Fallido',
            'cancelled' => 'Cancelado',
            'expired' => 'Expirado',
            'refunded', 'partially_refunded' => 'Reembolsado',
            default => strtolower((string) $providerStatus) === 'denied'
                ? 'Fallido'
                : 'Desconocido',
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

    /** @return array<string, mixed>|null */
    private function clientPackageData(mixed $clientPackage): ?array
    {
        if (! $clientPackage) {
            return null;
        }

        return [
            'id' => $clientPackage->id,
            'package_product_id' => $clientPackage->package_product_id,
            'status' => $clientPackage->status?->value ?? $clientPackage->status,
            'total_sessions' => $clientPackage->total_sessions,
            'used_sessions' => $clientPackage->used_sessions,
        ];
    }
}
