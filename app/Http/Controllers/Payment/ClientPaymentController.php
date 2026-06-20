<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\ListClientPaymentsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\ClientPaymentMovementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientPaymentController extends Controller
{
    public function index(Request $request, ListClientPaymentsAction $action): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:100'],
            'provider' => ['nullable', 'string', Rule::in(['simulator', 'mercadopago', 'paypal'])],
            'kind' => ['nullable', 'string', Rule::in(['payment', 'payment_intent', 'all'])],
            'booking_id' => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'only_pending' => ['nullable', 'boolean'],
            'only_final' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:160'],
        ]);

        $payments = $action(
            client: auth('user_jwt')->user(),
            perPage: $request->integer('per_page', 10),
            page: $request->integer('page', 1),
            filters: [
                ...$request->only([
                    'status',
                    'provider',
                    'kind',
                    'booking_id',
                    'date_from',
                    'date_to',
                    'search',
                ]),
                'only_pending' => $request->boolean('only_pending'),
                'only_final' => $request->boolean('only_final'),
            ],
        );

        return response()->json([
            'payments' => ClientPaymentMovementResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }
}
