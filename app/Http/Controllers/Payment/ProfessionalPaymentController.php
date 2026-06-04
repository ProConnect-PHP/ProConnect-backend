<?php

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\ListProfessionalPaymentsAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Payment\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalPaymentController extends Controller
{
    public function index(Request $request, ListProfessionalPaymentsAction $action): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $professionalProfile = auth('user_jwt')->user()->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para ver pagos.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        $payments = $action(
            professionalProfile: $professionalProfile,
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
