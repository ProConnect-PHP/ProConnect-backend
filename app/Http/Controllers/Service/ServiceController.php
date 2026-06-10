<?php

namespace App\Http\Controllers\Service;

use App\Actions\Service\DeleteServiceAction;
use App\Actions\Service\ListMyServicesAction;
use App\Actions\Service\ShowServiceAction;
use App\Actions\Service\StoreServiceAction;
use App\Actions\Service\UpdateServiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\Service\ServiceResource;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ServiceController extends Controller
{
    public function my(
        ListMyServicesAction $action
    ): JsonResponse {

        return response()->json([
            'services' => ServiceResource::collection(
                $action()
            ),
        ]);
    }

    public function store(
        StoreServiceRequest $request,
        StoreServiceAction $action
    ): JsonResponse {
        Gate::authorize('create', Service::class);

        $service = $action($request);

        return response()->json([
            'message' => 'Servicio creado correctamente',
            'service' => new ServiceResource($service),
        ], Response::HTTP_CREATED);
    }

    public function show(
        Service $service,
        ShowServiceAction $action
    ): JsonResponse {

        Gate::authorize('view', $service);

        return response()->json([
            'service' => new ServiceResource(
                $action($service)
            ),
        ]);
    }

    public function update(
        UpdateServiceRequest $request,
        Service $service,
        UpdateServiceAction $action
    ): JsonResponse {

        Gate::authorize('update', $service);

        return response()->json([
            'message' => 'Servicio actualizado correctamente',
            'service' => new ServiceResource(
                $action($request, $service)
            ),
        ]);
    }

    public function destroy(
        Service $service,
        DeleteServiceAction $action
    ): JsonResponse {

        Gate::authorize('delete', $service);

        $action($service);

        return response()->json([
            'message' => 'Servicio eliminado correctamente',
        ]);
    }
}
