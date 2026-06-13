<?php

namespace App\Actions\Payment;

use App\DTOs\Payment\ProviderPaymentStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogActorMode;
use Symfony\Component\HttpFoundation\Response;

final readonly class SimulatePaymentSuccessAction
{
    public function __construct(
        private MarkPaymentSucceededAction $markPaymentSucceeded,
    ) {}

    public function __invoke(PaymentIntent $paymentIntent, User $client): Payment
    {
        $intent = PaymentIntent::query()->findOrFail($paymentIntent->id);

        $this->ensureSimulationAllowed($intent, $client);

        if ($intent->isExpired() || $intent->status === PaymentIntentStatus::Expired) {
            $intent->update(['status' => PaymentIntentStatus::Expired]);

            throw new ApiException(
                error: 'PaymentIntentExpired',
                message: 'El intento de pago expiro.',
                status: Response::HTTP_CONFLICT,
            );
        }

        return ($this->markPaymentSucceeded)(
            paymentIntent: $intent,
            providerStatus: new ProviderPaymentStatus(
                providerReference: $intent->provider_reference ?: 'sim_'.$intent->id,
                status: PaymentStatus::Succeeded,
                rawStatus: 'approved',
                providerPaymentId: $intent->provider_reference ?: 'sim_'.$intent->id,
                paymentIntentId: $intent->id,
                paidAt: now()->toAtomString(),
                metadata: ['mode' => 'simulator'],
            ),
            actingAs: ActivityLogActorMode::Client,
        );
    }

    private function ensureSimulationAllowed(
        PaymentIntent $intent,
        User $client
    ): void {
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
    }
}
