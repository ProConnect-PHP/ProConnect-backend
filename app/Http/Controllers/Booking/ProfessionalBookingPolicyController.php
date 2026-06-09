<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\EnsureDefaultProfessionalBookingPolicyAction;
use App\Actions\Booking\UpdateProfessionalBookingPolicyAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\UpdateBookingPolicyRequest;
use App\Http\Resources\Booking\ProfessionalBookingPolicyResource;
use App\Models\User\ProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalBookingPolicyController extends Controller
{
    public function show(
        EnsureDefaultProfessionalBookingPolicyAction $ensureDefaults
    ): JsonResponse {
        $policy = $ensureDefaults($this->professionalProfile())
            ->load(['reminderRules' => fn ($query) => $query->orderByDesc('minutes_before_start')]);

        return response()->json([
            'data' => new ProfessionalBookingPolicyResource($policy),
        ]);
    }

    public function update(
        UpdateBookingPolicyRequest $request,
        UpdateProfessionalBookingPolicyAction $action
    ): JsonResponse {
        $policy = $action(
            $this->professionalProfile(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Política de reservas actualizada correctamente.',
            'data' => new ProfessionalBookingPolicyResource($policy),
        ]);
    }

    private function professionalProfile(): ProfessionalProfile
    {
        $professional = auth('user_jwt')->user()->professionalProfile;

        if (! $professional) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para gestionar políticas de reservas.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        return $professional;
    }
}
