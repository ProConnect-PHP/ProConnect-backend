<?php

namespace App\Http\Controllers\Video;

use App\Actions\Video\EnsureVideoSessionForBookingAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Video\VideoSessionResource;
use App\Models\Booking\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BookingVideoSessionController extends Controller
{
    public function show(Booking $booking): JsonResponse
    {
        Gate::authorize('view', $booking);

        $videoSession = $booking->videoSession()
            ->with(['booking', 'participants'])
            ->first();

        if (! $videoSession) {
            throw new ApiException(
                error: 'VideoSessionNotFound',
                message: 'Esta reserva no tiene sesion virtual.',
                status: Response::HTTP_NOT_FOUND
            );
        }

        return response()->json([
            'video_session' => new VideoSessionResource($videoSession),
        ]);
    }

    public function store(
        Booking $booking,
        EnsureVideoSessionForBookingAction $action
    ): JsonResponse {
        Gate::authorize('view', $booking);

        $videoSession = $action($booking);

        return response()->json([
            'message' => 'Sesion virtual creada correctamente.',
            'video_session' => new VideoSessionResource($videoSession),
        ], Response::HTTP_CREATED);
    }
}
