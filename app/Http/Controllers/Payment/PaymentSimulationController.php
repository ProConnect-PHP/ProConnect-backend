<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\SimulatePaymentFailureAction;
use App\Actions\Payment\SimulatePaymentSuccessAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\SimulatePaymentFailureRequest;
use App\Http\Resources\Payment\PaymentIntentResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\JsonResponse;

class PaymentSimulationController extends Controller
{
    public function success(
        PaymentIntent $paymentIntent,
        SimulatePaymentSuccessAction $action
    ): JsonResponse {
        $payment = $action(
            paymentIntent: $paymentIntent,
            client: auth('user_jwt')->user()
        );

        return response()->json([
            'message' => 'Pago simulado aprobado correctamente.',
            'payment' => new PaymentResource($payment),
        ]);
    }

    public function failure(
        PaymentIntent $paymentIntent,
        SimulatePaymentFailureRequest $request,
        SimulatePaymentFailureAction $action
    ): JsonResponse {
        $intent = $action(
            paymentIntent: $paymentIntent,
            client: auth('user_jwt')->user(),
            reason: $request->validated('failure_reason')
        );

        return response()->json([
            'message' => 'Pago simulado fallido.',
            'payment_intent' => new PaymentIntentResource($intent),
        ]);
    }
}
