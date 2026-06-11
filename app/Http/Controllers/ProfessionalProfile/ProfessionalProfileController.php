<?php

namespace App\Http\Controllers\ProfessionalProfile;

use App\Actions\ProfessionalProfile\ShowProfessionalProfileAction;
use App\Actions\ProfessionalProfile\StoreProfessionalProfileAction;
use App\Actions\ProfessionalProfile\UpdateProfessionalProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalProfile\StoreProfessionalProfileRequest;
use App\Http\Requests\ProfessionalProfile\UpdateProfessionalProfileRequest;
use App\Http\Resources\ProfessionalProfile\ProfessionalProfileResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProfessionalProfileController extends Controller
{
    public function show(
        ShowProfessionalProfileAction $showProfessionalProfileAction
    ): JsonResponse {

        $profile = $showProfessionalProfileAction();

        if (! $profile) {
            return response()->json([
                'message' => 'Professional profile not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'professional_profile' => new ProfessionalProfileResource($profile),
        ]);
    }

    public function store(
        StoreProfessionalProfileRequest $request,
        StoreProfessionalProfileAction $storeProfessionalProfileAction
    ): JsonResponse {

        $profile = $storeProfessionalProfileAction($request);

        return response()->json([
            'message' => 'Professional profile created successfully',
            'professional_profile' => new ProfessionalProfileResource($profile),
            'user' => new UserResource(auth('user_jwt')->user()->refresh()),
        ], Response::HTTP_CREATED);
    }

    public function update(
        UpdateProfessionalProfileRequest $request,
        UpdateProfessionalProfileAction $updateProfessionalProfileAction
    ): JsonResponse {

        $profile = $updateProfessionalProfileAction($request);

        return response()->json([
            'message' => 'Professional profile updated successfully',
            'professional_profile' => new ProfessionalProfileResource($profile),
        ]);
    }
}
