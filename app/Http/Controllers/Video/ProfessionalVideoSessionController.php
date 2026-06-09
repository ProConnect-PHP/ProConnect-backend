<?php

namespace App\Http\Controllers\Video;

use App\Actions\Video\ListProfessionalVideoSessionsAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Video\VideoSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalVideoSessionController extends Controller
{
    public function index(Request $request, ListProfessionalVideoSessionsAction $action): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $professionalProfile = auth('user_jwt')->user()->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para ver sesiones virtuales.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        $videoSessions = $action(
            professionalProfile: $professionalProfile,
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
