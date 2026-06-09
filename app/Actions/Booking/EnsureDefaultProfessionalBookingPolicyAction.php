<?php

namespace App\Actions\Booking;

use App\Models\Booking\ProfessionalBookingPolicy;
use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Models\User\ProfessionalProfile;
use Illuminate\Support\Facades\DB;

class EnsureDefaultProfessionalBookingPolicyAction
{
    public function __invoke(ProfessionalProfile $professional): ProfessionalBookingPolicy
    {
        return DB::transaction(function () use ($professional): ProfessionalBookingPolicy {
            $policy = ProfessionalBookingPolicy::query()->firstOrCreate(
                ['professional_id' => $professional->id],
                ProfessionalBookingPolicy::DEFAULTS
            );

            if ($policy->wasRecentlyCreated) {
                ProfessionalBookingReminderRule::query()->create([
                    'professional_id' => $professional->id,
                    'minutes_before_start' => 1440,
                    'send_email' => true,
                    'send_database_notification' => true,
                    'send_push' => false,
                    'send_whatsapp' => false,
                    'notify_client' => true,
                    'notify_professional' => false,
                    'is_active' => true,
                ]);

                ProfessionalBookingReminderRule::query()->create([
                    'professional_id' => $professional->id,
                    'minutes_before_start' => 120,
                    'send_email' => true,
                    'send_database_notification' => true,
                    'send_push' => false,
                    'send_whatsapp' => false,
                    'notify_client' => true,
                    'notify_professional' => true,
                    'is_active' => true,
                ]);
            }

            return $policy->load([
                'reminderRules' => fn ($query) => $query->orderByDesc('minutes_before_start'),
            ]);
        });
    }
}
