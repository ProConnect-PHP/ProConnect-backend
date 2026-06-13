<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\CreatePaymentIntentAction;
use App\Enums\Payment\PayableType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentIntentRequest;
use App\Http\Resources\Payment\PaymentIntentResource;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PaymentIntentController extends Controller
{
    public function store(
        StorePaymentIntentRequest $request,
        CreatePaymentIntentAction $action
    ): JsonResponse {
        $data = $request->validated();
        $payableType = PayableType::from($data['payable_type']);
        $payable = match ($payableType) {
            PayableType::Booking => Booking::query()->findOrFail($data['payable_id']),
            PayableType::Package => PackageProduct::query()->findOrFail($data['payable_id']),
        };

        Gate::authorize(
            $payable instanceof Booking ? 'pay' : 'purchase',
            $payable
        );

        $intent = $action(
            payable: $payable,
            client: auth('user_jwt')->user(),
            data: $data,
        );

        return response()->json([
            'message' => 'Intento de pago creado correctamente.',
            'payment_intent' => new PaymentIntentResource($intent),
        ], Response::HTTP_CREATED);
    }

    public function show(PaymentIntent $paymentIntent): JsonResponse
    {
        Gate::authorize('view', $paymentIntent);

        return $this->response($paymentIntent);
    }

    public function status(PaymentIntent $paymentIntent): JsonResponse
    {
        Gate::authorize('view', $paymentIntent);

        return $this->response($paymentIntent);
    }

    private function response(PaymentIntent $paymentIntent): JsonResponse
    {
        return response()->json([
            'payment_intent' => new PaymentIntentResource(
                $paymentIntent->load([
                    'booking',
                    'packageProduct',
                    'payment.clientPackage',
                ])
            ),
        ]);
    }
}
