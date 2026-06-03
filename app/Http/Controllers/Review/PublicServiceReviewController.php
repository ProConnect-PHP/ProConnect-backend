<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\ListServiceReviewsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicServiceReviewController extends Controller
{
    public function index(
        Service $service,
        Request $request,
        ListServiceReviewsAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $reviews = $action($service, (int) ($validated['per_page'] ?? 10));

        return response()->json([
            'reviews' => ReviewResource::collection($reviews->getCollection()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }
}
