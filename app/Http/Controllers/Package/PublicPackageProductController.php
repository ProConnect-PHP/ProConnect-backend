<?php

namespace App\Http\Controllers\Package;

use App\Actions\Package\ListPublicPackageProductsAction;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Package\PackageProductResource;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicPackageProductController extends Controller
{
    public function index(Request $request, ListPublicPackageProductsAction $action): JsonResponse
    {
        $filters = $request->validate([
            'service_id' => ['nullable', 'uuid', 'exists:services,id'],
            'professional_id' => ['nullable', 'uuid', 'exists:professional_profiles,id'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sessions_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->packageProductsResponse($action($filters));
    }

    public function byService(
        Service $service,
        Request $request,
        ListPublicPackageProductsAction $action
    ): JsonResponse {
        $filters = $request->validate([
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'sessions_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->packageProductsResponse($action($filters, $service));
    }

    public function show(PackageProduct $packageProduct): JsonResponse
    {
        if (! $packageProduct->is_active) {
            throw new ApiException(
                error: 'PackageNotAvailable',
                message: 'Este paquete no esta disponible.',
                status: Response::HTTP_CONFLICT
            );
        }

        return response()->json([
            'package_product' => new PackageProductResource(
                $packageProduct->load(['service', 'professional.user'])
            ),
        ]);
    }

    private function packageProductsResponse($packageProducts): JsonResponse
    {
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
}
