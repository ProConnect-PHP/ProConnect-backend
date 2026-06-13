<?php

namespace App\Services\Payment\Providers\PayPal;

use Illuminate\Http\Request;

final readonly class PayPalWebhookVerifier
{
    public function __construct(
        private PayPalClient $client,
    ) {}

    public function verify(Request $request): bool
    {
        return $this->client->verifyWebhookSignature($request);
    }
}
