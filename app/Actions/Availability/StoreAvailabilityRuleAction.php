<?php

namespace App\Actions\Availability;

use App\Exceptions\ApiException;
use App\Http\Requests\Availability\StoreAvailabilityRuleRequest;
use App\Models\Availability\AvailabilityRule;
use App\Models\Service\Service;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;
use Symfony\Component\HttpFoundation\Response;

class StoreAvailabilityRuleAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        Service $service,
        StoreAvailabilityRuleRequest $request
    ): AvailabilityRule {
        $data = $request->validated();

        $overlaps = AvailabilityRule::query()
            ->where('service_id', $service->id)
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_active', true)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlaps) {
            throw new ApiException(
                error: 'AvailabilityRuleOverlaps',
                message: 'Ya existe una regla de disponibilidad que se superpone con ese horario.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $rule = AvailabilityRule::create([
            ...$data,
            'service_id' => $service->id,
        ]);

        $this->activityLogger->record(
            event: ActivityLogEvent::AvailabilityCreated,
            entityType: 'availability_rule',
            entityId: $rule->id,
            entityOwnerId: $service->professional_id,
            metadata: [
                'service_id' => $service->id,
                'professional_id' => $service->professional_id,
                'day_of_week' => $rule->day_of_week,
                'start_time' => $rule->start_time,
                'end_time' => $rule->end_time,
                'is_active' => $rule->is_active,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $rule;
    }
}
