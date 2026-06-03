<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\DeleteReviewCommentAction;
use App\Actions\Review\UpdateReviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Review\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function update(
        Review $review,
        UpdateReviewRequest $request,
        UpdateReviewAction $action
    ): JsonResponse {
        Gate::authorize('update', $review);

        $review = $action($review, auth('user_jwt')->user(), $request->validated());

        return response()->json([
            'message' => 'Reseña actualizada correctamente',
            'review' => new ReviewResource($review),
        ]);
    }

    public function destroy(
        Review $review,
        DeleteReviewCommentAction $action
    ): JsonResponse {
        Gate::authorize('delete', $review);

        $review = $action($review, auth('user_jwt')->user());

        return response()->json([
            'message' => 'Comentario eliminado correctamente',
            'review' => new ReviewResource($review),
        ]);
    }
}
