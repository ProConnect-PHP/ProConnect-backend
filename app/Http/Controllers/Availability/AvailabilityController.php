<?php

namespace App\Http\Controllers\Availability;

use App\Actions\Availability\GenerateAvailabilitySlotsAction;
use App\Http\Controllers\Controller;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AvailabilityController extends Controller
{
    public function show(
        Service $service,
        Request $request,
        GenerateAvailabilitySlotsAction $action
    ): JsonResponse {
        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = $request->query('date');

        $slots = $action(
            service: $service,
            requestedDate: $date
        );

        return response()->json([
            'service_id' => $service->id,
            'date' => $date,
            'slots' => $slots,
        ], Response::HTTP_OK);
    }
}
