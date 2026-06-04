<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\ListClientPaymentsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientPaymentController extends Controller
{
    public function index(Request $request, ListClientPaymentsAction $action): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $payments = $action(
            client: auth('user_jwt')->user(),
            perPage: $request->integer('per_page', 10)
        );

        return response()->json([
            'payments' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
        ]);
    }
}
