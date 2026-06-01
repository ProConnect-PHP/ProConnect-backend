<?php

namespace App\Http\Controllers\Availability;

use App\Actions\Availability\DeleteAvailabilityRuleAction;
use App\Actions\Availability\ListAvailabilityRulesAction;
use App\Actions\Availability\StoreAvailabilityRuleAction;
use App\Actions\Availability\UpdateAvailabilityRuleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\StoreAvailabilityRuleRequest;
use App\Http\Requests\Availability\UpdateAvailabilityRuleRequest;
use App\Http\Resources\Availability\AvailabilityRuleResource;
use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AvailabilityRuleController extends Controller
{
    public function index(
        Service $service,
        ListAvailabilityRulesAction $action
    ): JsonResponse {
        Gate::authorize('view', $service);

        return response()->json([
            'availability_rules' => AvailabilityRuleResource::collection(
                $action($service)
            ),
        ]);
    }

    public function store(
        Service $service,
        StoreAvailabilityRuleRequest $request,
        StoreAvailabilityRuleAction $action
    ): JsonResponse {
        Gate::authorize('update', $service);

        $rule = $action($service, $request);

        return response()->json([
            'message' => 'Availability rule created successfully',
            'availability_rule' => new AvailabilityRuleResource($rule),
        ], Response::HTTP_CREATED);
    }

    public function update(
        AvailabilityRule $availabilityRule,
        UpdateAvailabilityRuleRequest $request,
        UpdateAvailabilityRuleAction $action
    ): JsonResponse {
        Gate::authorize('update', $availabilityRule->service);

        $rule = $action($availabilityRule, $request);

        return response()->json([
            'message' => 'Availability rule updated successfully',
            'availability_rule' => new AvailabilityRuleResource($rule),
        ]);
    }

    public function destroy(
        AvailabilityRule $availabilityRule,
        DeleteAvailabilityRuleAction $action
    ): JsonResponse {
        Gate::authorize('delete', $availabilityRule->service);

        $action($availabilityRule);

        return response()->json([
            'message' => 'Availability rule deleted successfully',
        ]);
    }
}
