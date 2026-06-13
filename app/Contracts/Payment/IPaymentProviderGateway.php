<?php

namespace App\Contracts\Payment;

use App\DTOs\Payment\PaymentCheckoutData;
use App\DTOs\Payment\ProviderPaymentStatus;
use App\DTOs\Payment\ProviderWebhookData;
use App\Enums\Payment\PaymentProvider;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\Request;

interface IPaymentProviderGateway
{
    public function provider(): PaymentProvider;

    public function createCheckout(PaymentIntent $intent): PaymentCheckoutData;

    public function fetchPaymentStatus(string $providerReference): ProviderPaymentStatus;

    public function parseWebhook(Request $request): ProviderWebhookData;
}
