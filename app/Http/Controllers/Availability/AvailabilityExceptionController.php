<?php

namespace App\Http\Controllers\Availability;

use App\Actions\Availability\DeleteAvailabilityExceptionAction;
use App\Actions\Availability\ListAvailabilityExceptionsAction;
use App\Actions\Availability\StoreAvailabilityExceptionAction;
use App\Actions\Availability\UpdateAvailabilityExceptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\StoreAvailabilityExceptionRequest;
use App\Http\Requests\Availability\UpdateAvailabilityExceptionRequest;
use App\Http\Resources\Availability\AvailabilityExceptionResource;
use App\Models\Availability\AvailabilityException;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AvailabilityExceptionController extends Controller
{
    public function index(
        Service $service,
        ListAvailabilityExceptionsAction $action
    ): JsonResponse {
        Gate::authorize('view', $service);

        return response()->json([
            'availability_exceptions' => AvailabilityExceptionResource::collection(
                $action($service)
            ),
        ]);
    }

    public function store(
        Service $service,
        StoreAvailabilityExceptionRequest $request,
        StoreAvailabilityExceptionAction $action
    ): JsonResponse {
        Gate::authorize('update', $service);

        $exception = $action($service, $request);

        return response()->json([
            'message' => 'Availability exception created successfully',
            'availability_exception' => new AvailabilityExceptionResource($exception),
        ], Response::HTTP_CREATED);
    }

    public function update(
        AvailabilityException $availabilityException,
        UpdateAvailabilityExceptionRequest $request,
        UpdateAvailabilityExceptionAction $action
    ): JsonResponse {
        Gate::authorize('update', $availabilityException->service);

        $exception = $action($availabilityException, $request);

        return response()->json([
            'message' => 'Availability exception updated successfully',
            'availability_exception' => new AvailabilityExceptionResource($exception),
        ]);
    }

    public function destroy(
        AvailabilityException $availabilityException,
        DeleteAvailabilityExceptionAction $action
    ): JsonResponse {
        Gate::authorize('delete', $availabilityException->service);

        $action($availabilityException);

        return response()->json([
            'message' => 'Availability exception deleted successfully',
        ]);
    }
}
