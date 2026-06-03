<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\CreateReviewReplyAction;
use App\Actions\Review\UpdateReviewReplyAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewReplyRequest;
use App\Http\Requests\Review\UpdateReviewReplyRequest;
use App\Http\Resources\Review\ReviewReplyResource;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReviewReplyController extends Controller
{
    public function store(
        Review $review,
        StoreReviewReplyRequest $request,
        CreateReviewReplyAction $action
    ): JsonResponse {
        $professionalProfile = auth('user_jwt')->user()->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para responder reseñas.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        Gate::authorize('reply', $review);

        $reply = $action(
            review: $review,
            professionalProfile: $professionalProfile,
            data: $request->validated()
        );

        return response()->json([
            'message' => 'Respuesta creada correctamente',
            'reply' => new ReviewReplyResource($reply),
        ], Response::HTTP_CREATED);
    }

    public function update(
        ReviewReply $reply,
        UpdateReviewReplyRequest $request,
        UpdateReviewReplyAction $action
    ): JsonResponse {
        $professionalProfile = auth('user_jwt')->user()->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para responder reseñas.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        Gate::authorize('update', $reply);

        $reply = $action(
            reply: $reply,
            professionalProfile: $professionalProfile,
            data: $request->validated()
        );

        return response()->json([
            'message' => 'Respuesta actualizada correctamente',
            'reply' => new ReviewReplyResource($reply),
        ]);
    }
}
