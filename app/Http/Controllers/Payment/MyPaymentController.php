<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\ListMyPaymentsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\PaymentHistoryDetailResource;
use App\Http\Resources\Payment\PaymentHistoryItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'data' => PaymentHistoryItemResource::collection($payments->getCollection())
                ->resolve($request),
            'links' => [
                'first' => $payments->url(1),
                'last' => $payments->url($payments->lastPage()),
                'prev' => $payments->previousPageUrl(),
                'next' => $payments->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $payments->currentPage(),
                'from' => $payments->firstItem(),
                'last_page' => $payments->lastPage(),
                'path' => $payments->path(),
                'per_page' => $payments->perPage(),
                'to' => $payments->lastItem(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function show(
        Request $request,
        string $id,
        ListMyPaymentsAction $action,
    ): JsonResponse {
        $client = $request->user('user_jwt');
        $item = $action->find($client, $id);

        abort_unless($item !== null, 404);

        $relatedAttempts = $action->relatedAttemptsFor($client, $item);

        return response()->json(
            (new PaymentHistoryDetailResource($item, $relatedAttempts))->resolve($request)
        );
    }
}
