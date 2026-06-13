<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\ProcessPaymentWebhookAction;
use App\Enums\Payment\PaymentProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    public function mercadoPago(
        Request $request,
        ProcessPaymentWebhookAction $action
    ): JsonResponse {
        $action(PaymentProvider::MercadoPago, $request);

        return response()->json(['received' => true]);
    }

    public function payPal(
        Request $request,
        ProcessPaymentWebhookAction $action
    ): JsonResponse {
        $action(PaymentProvider::PayPal, $request);

        return response()->json(['received' => true]);
    }
}
