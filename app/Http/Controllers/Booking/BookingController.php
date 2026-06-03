<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\CancelBookingAction;
use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\ListMyBookingsAction;
use App\Actions\Booking\RescheduleBookingAction;
use App\Actions\Booking\ShowBookingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\RescheduleBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Http\Resources\Booking\BookingResource;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function store(
        Service $service,
        StoreBookingRequest $request,
        CreateBookingAction $action
    ): JsonResponse {
        $booking = $action(
            service: $service,
            client: auth('user_jwt')->user(),
            startsAt: $request->validated('starts_at')
        );

        return response()->json([
            'message' => 'Reserva creada correctamente',
            'booking' => new BookingResource($booking),
        ], Response::HTTP_CREATED);
    }

    public function my(ListMyBookingsAction $action): JsonResponse
    {
        return response()->json([
            'bookings' => BookingResource::collection(
                $action(auth('user_jwt')->user())
            ),
        ]);
    }

    public function show(
        Booking $booking,
        ShowBookingAction $action
    ): JsonResponse {
        Gate::authorize('view', $booking);

        return response()->json([
            'booking' => new BookingResource($action($booking)),
        ]);
    }

    public function cancel(
        Booking $booking,
        CancelBookingRequest $request,
        CancelBookingAction $action
    ): JsonResponse {
        Gate::authorize('cancel', $booking);

        return response()->json([
            'message' => 'Reserva cancelada correctamente',
            'booking' => new BookingResource(
                $action(
                    booking: $booking,
                    actor: auth('user_jwt')->user(),
                    reason: $request->validated('reason')
                )
            ),
        ]);
    }

    public function reschedule(
        Booking $booking,
        RescheduleBookingRequest $request,
        RescheduleBookingAction $action
    ): JsonResponse {
        Gate::authorize('reschedule', $booking);

        return response()->json([
            'message' => 'Reserva reprogramada correctamente',
            'booking' => new BookingResource(
                $action(
                    booking: $booking,
                    actor: auth('user_jwt')->user(),
                    startsAt: $request->validated('starts_at'),
                    reason: $request->validated('reason')
                )
            ),
        ]);
    }
}
