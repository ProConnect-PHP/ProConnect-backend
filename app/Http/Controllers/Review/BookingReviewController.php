<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\CreateReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Booking\Booking;
use App\Models\Review\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BookingReviewController extends Controller
{
    public function store(
        Booking $booking,
        StoreReviewRequest $request,
        CreateReviewAction $action
    ): JsonResponse {
        Gate::authorize('create', [Review::class, $booking]);

        $review = $action(
            booking: $booking,
            client: auth('user_jwt')->user(),
            data: $request->validated()
        );

        return response()->json([
            'message' => 'Reseña creada correctamente',
            'review' => new ReviewResource($review),
        ], Response::HTTP_CREATED);
    }
}
