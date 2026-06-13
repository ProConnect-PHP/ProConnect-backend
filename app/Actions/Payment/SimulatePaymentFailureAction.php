<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\ProviderPaymentStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogActorMode;
use Symfony\Component\HttpFoundation\Response;

final readonly class SimulatePaymentFailureAction
{
    public function __construct(
        private MarkPaymentFailedAction $markPaymentFailed,
    ) {}

    public function __invoke(
        PaymentIntent $paymentIntent,
        User $client,
        ?string $reason = null
    ): PaymentIntent {
        $intent = PaymentIntent::query()->findOrFail($paymentIntent->id);

        if ($intent->client_id !== $client->id) {
            throw new ApiException(
                error: 'Forbidden',
                message: 'No puedes realizar esta operacion de pago.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($intent->provider !== PaymentProvider::Simulator) {
            throw new ApiException(
                error: 'PaymentProviderNotSimulatable',
                message: 'Este proveedor no admite simulacion.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if (! config('proconnect.payments.simulator.enabled')) {
            throw new ApiException(
                error: 'PaymentSimulatorDisabled',
                message: 'El simulador de pagos no esta habilitado.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($intent->status === PaymentIntentStatus::Failed) {
            return $intent->load(['booking', 'packageProduct', 'payment']);
        }

        if ($intent->isExpired() || $intent->status === PaymentIntentStatus::Expired) {
            $intent->update(['status' => PaymentIntentStatus::Expired]);

            throw new ApiException(
                error: 'PaymentIntentExpired',
                message: 'El intento de pago expiro.',
                status: Response::HTTP_CONFLICT,
            );
        }

        if (! in_array($intent->status, [
            PaymentIntentStatus::Pending,
            PaymentIntentStatus::CheckoutCreated,
            PaymentIntentStatus::Processing,
        ], true)) {
            throw new ApiException(
                error: 'PaymentIntentNotProcessable',
                message: 'Este intento de pago no puede procesarse.',
                status: Response::HTTP_CONFLICT,
            );
        }

        return ($this->markPaymentFailed)(
            paymentIntent: $intent,
            providerStatus: new ProviderPaymentStatus(
                providerReference: $intent->provider_reference ?: 'sim_'.$intent->id,
                status: PaymentStatus::Rejected,
                rawStatus: 'rejected',
                paymentIntentId: $intent->id,
            ),
            actingAs: ActivityLogActorMode::Client,
            reason: $reason ?? 'Pago simulado rechazado.',
        );
    }
}
