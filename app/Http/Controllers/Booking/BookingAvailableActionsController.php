<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Http\Resources\Booking\BookingAvailableActionsResource;
use App\Models\Booking\Booking;
use App\Services\Booking\BookingAvailableActionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BookingAvailableActionsController extends Controller
{
    public function show(
        Booking $booking,
        BookingAvailableActionsService $service
    ): JsonResponse {
        Gate::authorize('viewAvailableActions', $booking);

        return response()->json([
            'data' => new BookingAvailableActionsResource(
                $service->getForClient($booking)
            ),
        ]);
    }
}
