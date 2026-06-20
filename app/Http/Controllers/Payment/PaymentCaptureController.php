<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\CapturePayPalPaymentIntentAction;
use App\Http\Controllers\Controller;
use App\Models\Payment\PaymentIntent;
use App\Services\Payment\PaymentIntentStatusPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentCaptureController extends Controller
{
    public function store(
        PaymentIntent $paymentIntent,
        CapturePayPalPaymentIntentAction $action,
        PaymentIntentStatusPayload $statusPayload,
    ): JsonResponse {
        Gate::authorize('capture', $paymentIntent);

        $intent = $action(
            paymentIntent: $paymentIntent,
            client: auth('user_jwt')->user(),
        );

        return response()->json($statusPayload->make($intent));
    }
}
