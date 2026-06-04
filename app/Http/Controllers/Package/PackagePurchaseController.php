<?php

namespace App\Http\Controllers\Package;

use App\Actions\Package\PurchasePackageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\PurchasePackageRequest;
use App\Http\Resources\Package\ClientPackageResource;
use App\Models\Package\PackageProduct;
use Illuminate\Http\JsonResponse;

class PackagePurchaseController extends Controller
{
    public function store(
        PackageProduct $packageProduct,
        PurchasePackageRequest $request,
        PurchasePackageAction $action
    ): JsonResponse {
        $clientPackage = $action(
            packageProduct: $packageProduct,
            client: auth('user_jwt')->user(),
            data: $request->validated()
        );

        return response()->json([
            'message' => 'Paquete adquirido correctamente.',
            'client_package' => new ClientPackageResource($clientPackage),
        ]);
    }
}
