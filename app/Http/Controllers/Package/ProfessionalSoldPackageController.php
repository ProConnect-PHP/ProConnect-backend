<?php

namespace App\Http\Controllers\Package;

use App\Actions\Package\ListProfessionalSoldPackagesAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Package\ClientPackageResource;
use App\Models\Package\ClientPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalSoldPackageController extends Controller
{
    public function index(Request $request, ListProfessionalSoldPackagesAction $action): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:active,depleted,expired,cancelled'],
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $professionalProfile = $this->professionalProfile();
        $clientPackages = $action($professionalProfile, $filters);

        return response()->json([
            'client_packages' => ClientPackageResource::collection($clientPackages),
            'meta' => [
                'current_page' => $clientPackages->currentPage(),
                'per_page' => $clientPackages->perPage(),
                'total' => $clientPackages->total(),
                'last_page' => $clientPackages->lastPage(),
            ],
        ]);
    }

    public function show(ClientPackage $clientPackage): JsonResponse
    {
        Gate::authorize('view', $clientPackage);

        return response()->json([
            'client_package' => new ClientPackageResource(
                $clientPackage->load(['packageProduct.service', 'service', 'sessions.booking'])
            ),
        ]);
    }

    private function professionalProfile()
    {
        $professionalProfile = auth('user_jwt')->user()->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para ver paquetes vendidos.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        return $professionalProfile;
    }
}
