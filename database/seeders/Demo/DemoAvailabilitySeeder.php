<?php

namespace Database\Seeders\Demo;

use App\Models\User\User;
use App\Models\Availability\AvailabilityRule;
use App\Models\Availability\AvailabilityException;
use Illuminate\Database\Seeder;

/**
 * Seeds demo availability rules and exceptions.
 *
 * Creates recurring weekly availability rules for each active service.
 * Also creates date exceptions for unavailable days or alternative hours.
 *
 * ISO day_of_week: 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri, 6=Sat, 7=Sun
 */
class DemoAvailabilitySeeder extends Seeder
{
    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $this->createPsychologistAvailability();
        $this->createCoachAvailability();
        $this->createNutritionistAvailability();
        $this->createConsultantAvailability();

        $this->createAvailabilityExceptions();

        $this->command?->info('✓ Demo availability rules and exceptions created/updated');
    }

    /**
     * Availability rules for psychologist services.
     */
    private function createPsychologistAvailability(): void
    {
        $user = User::where('email', 'psicologa@proconnect.test')->first();
        if (!$user || !$user->professionalProfile) return;

        $services = $user->professionalProfile->services()->where('is_active', true)->get();

        foreach ($services as $service) {
            // Monday 09:00-13:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 1,
                ],
                [
                    'start_time' => '09:00',
                    'end_time' => '13:00',
                    'is_active' => true,
                ]
            );

            // Wednesday 14:00-18:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 3,
                ],
                [
                    'start_time' => '14:00',
                    'end_time' => '18:00',
                    'is_active' => true,
                ]
            );

            // Friday 09:00-12:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 5,
                ],
                [
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Availability rules for coach services.
     */
    private function createCoachAvailability(): void
    {
        $user = User::where('email', 'coach@proconnect.test')->first();
        if (!$user || !$user->professionalProfile) return;

        $services = $user->professionalProfile->services()->where('is_active', true)->get();

        foreach ($services as $service) {
            // Tuesday 10:00-16:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 2,
                ],
                [
                    'start_time' => '10:00',
                    'end_time' => '16:00',
                    'is_active' => true,
                ]
            );

            // Thursday 10:00-16:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 4,
                ],
                [
                    'start_time' => '10:00',
                    'end_time' => '16:00',
                    'is_active' => true,
                ]
            );

            // Saturday 09:00-12:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 6,
                ],
                [
                    'start_time' => '09:00',
                    'end_time' => '12:00',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Availability rules for nutritionist services.
     */
    private function createNutritionistAvailability(): void
    {
        $user = User::where('email', 'nutricionista@proconnect.test')->first();
        if (!$user || !$user->professionalProfile) return;

        $services = $user->professionalProfile->services()->where('is_active', true)->get();

        foreach ($services as $service) {
            // Monday 08:00-12:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 1,
                ],
                [
                    'start_time' => '08:00',
                    'end_time' => '12:00',
                    'is_active' => true,
                ]
            );

            // Tuesday 14:00-18:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 2,
                ],
                [
                    'start_time' => '14:00',
                    'end_time' => '18:00',
                    'is_active' => true,
                ]
            );

            // Thursday 08:00-12:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 4,
                ],
                [
                    'start_time' => '08:00',
                    'end_time' => '12:00',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Availability rules for consultant services.
     */
    private function createConsultantAvailability(): void
    {
        $user = User::where('email', 'consultor@proconnect.test')->first();
        if (!$user || !$user->professionalProfile) return;

        $services = $user->professionalProfile->services()->where('is_active', true)->get();

        foreach ($services as $service) {
            // Wednesday 09:00-17:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 3,
                ],
                [
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'is_active' => true,
                ]
            );

            // Friday 10:00-16:00
            AvailabilityRule::updateOrCreate(
                [
                    'service_id' => $service->id,
                    'day_of_week' => 5,
                ],
                [
                    'start_time' => '10:00',
                    'end_time' => '16:00',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Create availability exceptions for demo purposes.
     */
    private function createAvailabilityExceptions(): void
    {
        // Get a sample active service to create exceptions
        $user = User::where('email', 'psicologa@proconnect.test')->first();
        if (!$user || !$user->professionalProfile) return;

        $service = $user->professionalProfile->services()->where('is_active', true)->first();
        if (!$service) return;

        // Exception 1: Completely unavailable day (3 days from now)
        $unavailableDate = now()->addDays(3)->toDateString();
        AvailabilityException::updateOrCreate(
            [
                'service_id' => $service->id,
                'exception_date' => $unavailableDate,
            ],
            [
                'is_unavailable' => true,
                'reason' => 'Congreso profesional',
            ]
        );

        // Exception 2: Alternative hours (5 days from now)
        $alternativeDate = now()->addDays(5)->toDateString();
        AvailabilityException::updateOrCreate(
            [
                'service_id' => $service->id,
                'exception_date' => $alternativeDate,
            ],
            [
                'is_unavailable' => false,
                'alt_start' => '15:00',
                'alt_end' => '19:00',
                'reason' => 'Horario especial - evento profesional',
            ]
        );
    }
}
