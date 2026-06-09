<?php

namespace App\Http\Controllers\Video;

use App\Actions\Video\JoinVideoSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Video\VideoSessionJoinResource;
use App\Models\Video\VideoSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VideoSessionJoinController extends Controller
{
    public function store(
        VideoSession $videoSession,
        JoinVideoSessionAction $action
    ): JsonResponse {
        Gate::authorize('join', $videoSession);

        $joinData = $action(
            videoSession: $videoSession,
            user: auth('user_jwt')->user()
        );

        return response()->json([
            'message' => 'Acceso a sesion virtual generado correctamente.',
            'join' => new VideoSessionJoinResource($joinData),
        ]);
    }
}
