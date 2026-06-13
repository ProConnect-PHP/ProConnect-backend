<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\CreatePaymentCheckoutAction;
use App\Enums\Payment\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentCheckoutRequest;
use App\Http\Resources\Payment\PaymentIntentResource;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PaymentCheckoutController extends Controller
{
    public function store(
        PaymentIntent $paymentIntent,
        CreatePaymentCheckoutRequest $request,
        CreatePaymentCheckoutAction $action
    ): JsonResponse {
        Gate::authorize('checkout', $paymentIntent);

        $intent = $action(
            paymentIntent: $paymentIntent,
            provider: PaymentProvider::from($request->validated('provider')),
            client: auth('user_jwt')->user(),
        );

        return response()->json([
            'message' => 'Checkout creado correctamente.',
            'payment_intent' => new PaymentIntentResource($intent),
        ]);
    }
}
