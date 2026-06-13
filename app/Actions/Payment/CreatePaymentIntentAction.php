<?php

namespace App\Actions\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PayableType;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Services\Payment\PaymentPayloadSanitizer;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class CreatePaymentIntentAction
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private PaymentPayloadSanitizer $sanitizer,
    ) {}

    public function __invoke(
        Booking|PackageProduct $payable,
        User $client,
        array $data = []
    ): PaymentIntent {
        $provider = $this->resolveProvider($data['provider'] ?? null);

        $intent = DB::transaction(function () use (
            $payable,
            $client,
            $data,
            $provider
        ): PaymentIntent {
            return $payable instanceof Booking
                ? $this->createForBooking($payable, $client, $data, $provider)
                : $this->createForPackage($payable, $client, $data, $provider);
        });

        if ($intent->wasRecentlyCreated) {
            $this->activityLogger->record(
                event: ActivityLogEvent::PaymentCreated,
                entityType: 'payment_intent',
                entityId: $intent->id,
                entityOwnerId: $intent->professional_id,
                metadata: $this->activityMetadata($intent),
                actor: $client,
                actingAs: ActivityLogActorMode::Client,
            );
        }

        return $intent;
    }

    private function createForBooking(
        Booking $booking,
        User $client,
        array $data,
        PaymentProvider $provider
    ): PaymentIntent {
        $booking = Booking::query()
            ->with(['payment', 'paymentIntents'])
            ->whereKey($booking->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($booking->client_id !== $client->id) {
            throw new ApiException(
                error: 'Forbidden',
                message: 'No puedes pagar esta reserva.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($booking->status === BookingStatus::Paid || $booking->payment()->exists()) {
            throw new ApiException(
                error: 'BookingAlreadyPaid',
                message: 'Esta reserva ya fue pagada.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            throw new ApiException(
                error: 'BookingNotPayable',
                message: 'Solo puedes pagar reservas confirmadas.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if ($booking->client_package_id !== null) {
            throw new ApiException(
                error: 'BookingNotPayable',
                message: 'Esta reserva esta cubierta por un paquete.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $existingIntent = $booking->paymentIntents()
            ->whereIn('status', $this->activeIntentStatuses())
            ->latest()
            ->first();

        if ($existingIntent instanceof PaymentIntent && ! $existingIntent->isExpired()) {
            return $existingIntent->load(['booking', 'packageProduct', 'payment']);
        }

        if ($existingIntent instanceof PaymentIntent) {
            $existingIntent->update(['status' => PaymentIntentStatus::Expired]);
        }

        if ($booking->paymentIntents()
            ->where('status', PaymentIntentStatus::Succeeded->value)
            ->exists()) {
            throw new ApiException(
                error: 'BookingAlreadyPaid',
                message: 'Esta reserva ya fue pagada.',
                status: Response::HTTP_CONFLICT,
            );
        }

        return PaymentIntent::create([
            'booking_id' => $booking->id,
            'package_product_id' => null,
            'payable_type' => PayableType::Booking,
            'payable_id' => $booking->id,
            'client_id' => $client->id,
            'professional_id' => $booking->professional_id,
            'provider' => $provider,
            'status' => PaymentIntentStatus::Pending,
            'amount' => (int) round((float) $booking->price_snapshot),
            'currency' => config('proconnect.payments.currency', 'UYU'),
            'provider_reference' => null,
            'checkout_url' => null,
            'metadata' => $this->sanitizer->sanitize([
                ...($data['metadata'] ?? []),
                'price_snapshot' => $booking->price_snapshot,
                'service_id' => $booking->service_id,
            ]),
            'expires_at' => $this->expiration(),
        ])->load(['booking', 'packageProduct', 'payment']);
    }

    private function createForPackage(
        PackageProduct $packageProduct,
        User $client,
        array $data,
        PaymentProvider $provider
    ): PaymentIntent {
        $packageProduct = PackageProduct::query()
            ->whereKey($packageProduct->id)
            ->lockForUpdate()
            ->firstOrFail();

        if (! $packageProduct->is_active) {
            throw new ApiException(
                error: 'PackageNotAvailable',
                message: 'Este paquete no esta disponible.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if ($client->professionalProfile?->id === $packageProduct->professional_id) {
            throw new ApiException(
                error: 'CannotPurchaseOwnPackage',
                message: 'No puedes comprar tu propio paquete.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if (ClientPackage::query()
            ->where('package_product_id', $packageProduct->id)
            ->where('client_id', $client->id)
            ->exists()) {
            throw new ApiException(
                error: 'PackageAlreadyPurchased',
                message: 'Ya has adquirido este paquete.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $existingIntent = PaymentIntent::query()
            ->where('package_product_id', $packageProduct->id)
            ->where('client_id', $client->id)
            ->whereIn('status', $this->activeIntentStatuses())
            ->latest()
            ->first();

        if ($existingIntent instanceof PaymentIntent && ! $existingIntent->isExpired()) {
            return $existingIntent->load(['booking', 'packageProduct', 'payment']);
        }

        if ($existingIntent instanceof PaymentIntent) {
            $existingIntent->update(['status' => PaymentIntentStatus::Expired]);
        }

        return PaymentIntent::create([
            'booking_id' => null,
            'package_product_id' => $packageProduct->id,
            'payable_type' => PayableType::Package,
            'payable_id' => $packageProduct->id,
            'client_id' => $client->id,
            'professional_id' => $packageProduct->professional_id,
            'provider' => $provider,
            'status' => PaymentIntentStatus::Pending,
            'amount' => $packageProduct->price,
            'currency' => $packageProduct->currency,
            'provider_reference' => null,
            'checkout_url' => null,
            'metadata' => $this->sanitizer->sanitize([
                ...($data['metadata'] ?? []),
                'package_name' => $packageProduct->name,
                'sessions_count' => $packageProduct->sessions_count,
                'validity_days' => $packageProduct->validity_days,
                'service_id' => $packageProduct->service_id,
            ]),
            'expires_at' => $this->expiration(),
        ])->load(['booking', 'packageProduct', 'payment']);
    }

    private function resolveProvider(mixed $provider): PaymentProvider
    {
        $providerValue = is_string($provider) && $provider !== ''
            ? $provider
            : (string) config('proconnect.payments.default_provider', 'simulator');
        $resolved = PaymentProvider::tryFrom($providerValue);
        $enabledProviders = config('proconnect.payments.enabled_providers', []);

        if (! $resolved || ! in_array($resolved->value, $enabledProviders, true)) {
            throw new ApiException(
                error: 'UnsupportedPaymentProvider',
                message: 'El proveedor de pago no esta habilitado.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (
            $resolved === PaymentProvider::Simulator
            && ! config('proconnect.payments.simulator.enabled')
        ) {
            throw new ApiException(
                error: 'PaymentSimulatorDisabled',
                message: 'El simulador de pagos no esta habilitado.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        return $resolved;
    }

    private function activeIntentStatuses(): array
    {
        return [
            PaymentIntentStatus::Pending->value,
            PaymentIntentStatus::CheckoutCreated->value,
            PaymentIntentStatus::Processing->value,
        ];
    }

    private function expiration(): mixed
    {
        return now()->addMinutes(
            (int) config('proconnect.payments.intent_expiration_minutes', 30)
        );
    }

    private function activityMetadata(PaymentIntent $intent): array
    {
        return [
            'payment_intent_id' => $intent->id,
            'client_id' => $intent->client_id,
            'professional_id' => $intent->professional_id,
            'booking_id' => $intent->booking_id,
            'package_product_id' => $intent->package_product_id,
            'payable_type' => $intent->payable_type,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'provider' => $intent->provider,
            'new_status' => $intent->status,
        ];
    }
}
