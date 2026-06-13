<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\CreatePaymentIntentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentIntentRequest;
use App\Http\Resources\Payment\PaymentIntentResource;
use App\Models\Booking\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BookingPaymentIntentController extends Controller
{
    public function store(
        Booking $booking,
        CreatePaymentIntentRequest $request,
        CreatePaymentIntentAction $action
    ): JsonResponse {
        Gate::authorize('pay', $booking);

        $intent = $action(
            payable: $booking,
            client: auth('user_jwt')->user(),
            data: $request->validated()
        );

        return response()->json([
            'message' => 'Intento de pago creado correctamente.',
            'payment_intent' => new PaymentIntentResource($intent),
        ], Response::HTTP_CREATED);
    }
}
