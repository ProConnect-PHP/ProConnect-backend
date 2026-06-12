<?php

namespace App\Actions\ProfessionalProfile;

use App\Http\Requests\ProfessionalProfile\UpdateProfessionalProfileRequest;
use App\Models\User\ProfessionalProfile;
use App\Support\ActivityLog\ActivityLogActorMode;
use App\Support\ActivityLog\ActivityLogEvent;
use App\Support\ActivityLog\ActivityLogger;

class UpdateProfessionalProfileAction
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function __invoke(
        UpdateProfessionalProfileRequest $request
    ): ProfessionalProfile {

        $profile = ProfessionalProfile::query()
            ->where('user_id', auth('user_jwt')->id())
            ->firstOrFail();

        $data = $request->validated();
        $profile->update($data);

        $profile = $profile->refresh();

        $this->activityLogger->record(
            event: ActivityLogEvent::ProfessionalProfileUpdated,
            entityType: 'professional_profile',
            entityId: $profile->id,
            entityOwnerId: $profile->user_id,
            metadata: [
                'profile_id' => $profile->id,
                'changed_fields' => array_keys($data),
                'bio_length' => array_key_exists('bio', $data)
                    ? mb_strlen((string) $data['bio'])
                    : null,
            ],
            actingAs: ActivityLogActorMode::Professional,
        );

        return $profile;
    }
}
