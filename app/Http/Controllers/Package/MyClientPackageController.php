<?php

namespace App\Http\Controllers\Package;

use App\Actions\Package\ListMyClientPackagesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Package\ClientPackageResource;
use App\Models\Package\ClientPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MyClientPackageController extends Controller
{
    public function index(Request $request, ListMyClientPackagesAction $action): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:active,depleted,expired,cancelled'],
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'professional_id' => ['nullable', 'uuid', 'exists:professional_profiles,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $clientPackages = $action(auth('user_jwt')->user(), $filters);

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
}
