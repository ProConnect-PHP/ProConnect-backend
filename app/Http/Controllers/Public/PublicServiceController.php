<?php

namespace App\Http\Controllers\Public;

use App\Actions\Public\ListPublicServicesAction;
use App\Actions\Public\ShowPublicServiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ListPublicServicesRequest;
use App\Http\Resources\Public\PublicServiceResource;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;

class PublicServiceController extends Controller
{
    public function index(
        ListPublicServicesRequest $request,
        ListPublicServicesAction $action
    ): JsonResponse {
        $services = $action($request->validated());

        return response()->json([
            'services' => PublicServiceResource::collection($services->getCollection()),
            'meta' => [
                'current_page' => $services->currentPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
                'last_page' => $services->lastPage(),
            ],
        ]);
    }

    public function show(
        Service $service,
        ShowPublicServiceAction $action
    ): JsonResponse {
        return response()->json([
            'service' => new PublicServiceResource($action($service)),
        ]);
    }
}
