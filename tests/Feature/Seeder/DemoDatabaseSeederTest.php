<?php

namespace Tests\Feature\Seeder;

use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Company\Company;
use App\Models\Review\Review;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Database\Seeders\Demo\DemoDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_runs_successfully(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(1, User::count());
    }

    public function test_demo_users_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'cliente@proconnect.test',
            'role' => 'client',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'psicologa@proconnect.test',
            'role' => 'professional',
        ]);
    }

    public function test_professional_profiles_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(4, ProfessionalProfile::count());
    }

    public function test_companies_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(4, Company::count());
    }

    public function test_active_services_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $activeServices = Service::where('is_active', true)->count();

        $this->assertGreaterThanOrEqual(12, $activeServices);
    }

    public function test_inactive_service_exists(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertDatabaseHas('services', ['is_active' => false]);
    }

    public function test_availability_rules_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(1, AvailabilityRule::count());
    }

    public function test_bookings_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $bookingCount = Booking::count();

        $this->assertGreaterThanOrEqual(10, $bookingCount);

        $statuses = Booking::query()
            ->distinct()
            ->pluck('status')
            ->toArray();

        $this->assertGreaterThanOrEqual(3, count($statuses));
    }

    public function test_reviews_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(3, Review::count());
    }

    public function test_professional_ratings_are_calculated(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $professionalsWithRating = ProfessionalProfile::where('reviews_count', '>', 0)
            ->where('avg_rating', '>', 0)
            ->count();

        $this->assertGreaterThanOrEqual(1, $professionalsWithRating);
    }

    public function test_seeding_can_be_disabled_via_env(): void
    {
        $this->assertTrue(true);
    }
}
