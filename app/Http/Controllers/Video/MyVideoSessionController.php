<?php

namespace App\Http\Controllers\Video;

use App\Actions\Video\ListMyVideoSessionsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Video\VideoSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyVideoSessionController extends Controller
{
    public function index(Request $request, ListMyVideoSessionsAction $action): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $videoSessions = $action(
            client: auth('user_jwt')->user(),
            perPage: $request->integer('per_page', 10)
        );

        return response()->json([
            'video_sessions' => VideoSessionResource::collection($videoSessions),
            'meta' => [
                'current_page' => $videoSessions->currentPage(),
                'per_page' => $videoSessions->perPage(),
                'total' => $videoSessions->total(),
                'last_page' => $videoSessions->lastPage(),
            ],
        ]);
    }
}
