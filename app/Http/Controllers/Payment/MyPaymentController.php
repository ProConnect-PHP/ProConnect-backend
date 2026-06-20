<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\ListMyPaymentsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\PaymentDetailResource;
use App\Http\Resources\Payment\PaymentResource;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class MyPaymentController extends Controller
{
    public function index(
        Request $request,
        ListMyPaymentsAction $action
    ): JsonResponse {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $payments = $action(
            client: $request->user('user_jwt'),
            perPage: $request->integer('per_page', 10),
            page: $request->integer('page', 1),
        );

        return PaymentResource::collection($payments)->response();
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        Gate::authorize('viewAsClient', $payment);

        $payment->load(['booking', 'packageProduct', 'clientPackage', 'intent']);

        $relatedAttempts = $this->relatedAttemptsFor($payment, $request);

        return response()->json(
            (new PaymentDetailResource($payment, $relatedAttempts))->resolve($request)
        );
    }

    /** @return Collection<int, PaymentIntent> */
    private function relatedAttemptsFor(Payment $payment, Request $request): Collection
    {
        $query = PaymentIntent::query()
            ->where('client_id', $request->user('user_jwt')->id);

        if ($payment->booking_id !== null) {
            return $query
                ->where('booking_id', $payment->booking_id)
                ->latest('created_at')
                ->get();
        }

        if ($payment->package_product_id !== null) {
            return $query
                ->where('package_product_id', $payment->package_product_id)
                ->latest('created_at')
                ->get();
        }

        return collect();
    }
}
