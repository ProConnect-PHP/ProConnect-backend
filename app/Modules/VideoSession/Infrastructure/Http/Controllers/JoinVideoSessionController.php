<?php

namespace App\Modules\VideoSession\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Booking\Booking;
use App\Models\User\User;
use App\Modules\VideoSession\Application\UseCases\GenerateVideoSessionTokenUseCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class JoinVideoSessionController extends Controller
{
    public function __invoke(
        Booking $booking,
        Request $request,
        GenerateVideoSessionTokenUseCase $useCase,
    ): JsonResponse {
        $user = $request->user('user_jwt');

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        $joinData = $useCase->execute(
            booking: $booking,
            user: $user,
        );

        return response()->json([
            'data' => $joinData->toArray(),
        ]);
    }
}
