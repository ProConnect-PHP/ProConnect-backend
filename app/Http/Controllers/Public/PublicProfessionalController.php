<?php

namespace App\Http\Controllers\Public;

use App\Actions\Public\ShowPublicProfessionalAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicProfessionalResource;
use App\Models\User\ProfessionalProfile;
use Illuminate\Http\JsonResponse;

class PublicProfessionalController extends Controller
{
    public function show(
        ProfessionalProfile $professionalProfile,
        ShowPublicProfessionalAction $action
    ): JsonResponse {
        return response()->json([
            'professional' => new PublicProfessionalResource($action($professionalProfile)),
        ]);
    }
}
