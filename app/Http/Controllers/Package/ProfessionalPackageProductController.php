<?php

namespace App\Http\Controllers\Package;

use App\Actions\Package\CreatePackageProductAction;
use App\Actions\Package\DeletePackageProductAction;
use App\Actions\Package\UpdatePackageProductAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\StorePackageProductRequest;
use App\Http\Requests\Package\UpdatePackageProductRequest;
use App\Http\Resources\Package\PackageProductResource;
use App\Models\Package\PackageProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalPackageProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $professionalProfile = $this->professionalProfile();

        $packageProducts = PackageProduct::query()
            ->with(['service', 'professional.user'])
            ->where('professional_id', $professionalProfile->id)
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'package_products' => PackageProductResource::collection($packageProducts),
            'meta' => [
                'current_page' => $packageProducts->currentPage(),
                'per_page' => $packageProducts->perPage(),
                'total' => $packageProducts->total(),
                'last_page' => $packageProducts->lastPage(),
            ],
        ]);
    }

    public function store(
        StorePackageProductRequest $request,
        CreatePackageProductAction $action
    ): JsonResponse {
        $packageProduct = $action(
            professionalProfile: $this->professionalProfile(),
            data: $request->validated()
        );

        return response()->json([
            'message' => 'Paquete creado correctamente.',
            'package_product' => new PackageProductResource($packageProduct),
        ], HttpResponse::HTTP_CREATED);
    }

    public function show(PackageProduct $packageProduct): JsonResponse
    {
        Gate::authorize('manage', $packageProduct);

        return response()->json([
            'package_product' => new PackageProductResource(
                $packageProduct->load(['service', 'professional.user'])
            ),
        ]);
    }

    public function update(
        PackageProduct $packageProduct,
        UpdatePackageProductRequest $request,
        UpdatePackageProductAction $action
    ): JsonResponse {
        Gate::authorize('update', $packageProduct);

        return response()->json([
            'message' => 'Paquete actualizado correctamente.',
            'package_product' => new PackageProductResource(
                $action($packageProduct, $request->validated())
            ),
        ]);
    }

    public function destroy(
        PackageProduct $packageProduct,
        DeletePackageProductAction $action
    ): JsonResponse {
        Gate::authorize('delete', $packageProduct);

        $action($packageProduct);

        return response()->json([
            'message' => 'Paquete eliminado correctamente.',
        ]);
    }

    private function professionalProfile()
    {
        $professionalProfile = auth('user_jwt')->user()->professionalProfile;

        if (! $professionalProfile) {
            throw new ApiException(
                error: 'ProfessionalProfileRequired',
                message: 'Necesitas un perfil profesional para gestionar paquetes.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        return $professionalProfile;
    }
}
