<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\CreateProfessionalBookingReminderRuleAction;
use App\Actions\Booking\DeleteProfessionalBookingReminderRuleAction;
use App\Actions\Booking\EnsureDefaultProfessionalBookingPolicyAction;
use App\Actions\Booking\UpdateProfessionalBookingReminderRuleAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StoreReminderRuleRequest;
use App\Http\Requests\Booking\UpdateReminderRuleRequest;
use App\Http\Resources\Booking\ProfessionalBookingReminderRuleResource;
use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Models\User\ProfessionalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProfessionalReminderRuleController extends Controller
{
    public function index(
        EnsureDefaultProfessionalBookingPolicyAction $ensureDefaults
    ): JsonResponse {
        $professional = $this->professionalProfile();
        $ensureDefaults($professional);

        return response()->json([
            'data' => ProfessionalBookingReminderRuleResource::collection(
                $professional->reminderRules()
                    ->orderByDesc('minutes_before_start')
                    ->get()
            ),
        ]);
    }

    public function store(
        StoreReminderRuleRequest $request,
        CreateProfessionalBookingReminderRuleAction $action
    ): JsonResponse {
        $rule = $action(
            $this->professionalProfile(),
            $request->validated()
        );

        return response()->json([
            'message' => 'Regla de recordatorio creada correctamente.',
            'data' => new ProfessionalBookingReminderRuleResource($rule),
        ], Response::HTTP_CREATED);
    }

    public function update(
        ProfessionalBookingReminderRule $reminderRule,
        UpdateReminderRuleRequest $request,
        UpdateProfessionalBookingReminderRuleAction $action
    ): JsonResponse {
        return response()->json([
            'message' => 'Regla de recordatorio actualizada correctamente.',
            'data' => new ProfessionalBookingReminderRuleResource(
                $action($reminderRule, $request->validated())
            ),
        ]);
    }

    public function destroy(
        ProfessionalBookingReminderRule $reminderRule,
        DeleteProfessionalBookingReminderRuleAction $action
    ): JsonResponse {
        Gate::authorize('delete', $reminderRule);
        $action($reminderRule);

        return response()->json([
            'message' => 'Regla de recordatorio eliminada correctamente.',
        ]);
    }

    private function professionalProfile(): ProfessionalProfile
    {
        $professional = auth('user_jwt')->user()->professionalProfile;

        if (! $professional) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para gestionar recordatorios.',
                status: SymfonyResponse::HTTP_FORBIDDEN
            );
        }

        return $professional;
    }
}
