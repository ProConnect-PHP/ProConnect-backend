<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\ConfirmBookingAction;
use App\Actions\Booking\CompleteBookingAction;
use App\Actions\Booking\ListProfessionalBookingsAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\BookingResource;
use App\Models\Booking\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalBookingController extends Controller
{
    public function index(ListProfessionalBookingsAction $action): JsonResponse
    {
        $professionalProfile = auth('user_jwt')->user()->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para ver reservas profesionales.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        return response()->json([
            'bookings' => BookingResource::collection($action($professionalProfile)),
        ]);
    }

    public function confirm(
        Booking $booking,
        ConfirmBookingAction $action
    ): JsonResponse {
        Gate::authorize('confirm', $booking);

        return response()->json([
            'message' => 'Reserva confirmada correctamente',
            'booking' => new BookingResource($action($booking)),
        ]);
    }

    public function complete(
        Booking $booking,
        CompleteBookingAction $action
    ): JsonResponse {
        Gate::authorize('complete', $booking);

        /** @var \App\Models\User\User $actor */
        $actor = auth('user_jwt')->user();

        return response()->json([
            'data' => new BookingResource($action($booking, $actor)),
            'message' => 'Booking completed successfully.',
        ]);
    }
}
