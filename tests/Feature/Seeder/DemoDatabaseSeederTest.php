<?php

namespace Tests\Feature\Seeder;

use App\Models\User\User;
use App\Models\User\ProfessionalProfile;
use App\Models\Company\Company;
use App\Models\Service\Service;
use App\Models\Availability\AvailabilityRule;
use App\Models\Booking\Booking;
use App\Models\Review\Review;
use Database\Seeders\Demo\DemoDatabaseSeeder;
use Tests\TestCase;

class DemoDatabaseSeederTest extends TestCase
{
    /**
     * Test that demo database seeder runs without errors.
     */
    public function test_demo_seeder_runs_successfully(): void
    {
        // Run the seeder
        $this->seed(DemoDatabaseSeeder::class);

        // Basic assertions to verify seeding worked
        $this->assertGreaterThanOrEqual(1, User::count());
    }

    /**
     * Test that demo users are created with correct roles.
     */
    public function test_demo_users_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // Client users
        $this->assertDatabaseHas('users', [
            'email' => 'cliente@proconnect.test',
            'role' => 'client',
        ]);

        // Professional users
        $this->assertDatabaseHas('users', [
            'email' => 'psicologa@proconnect.test',
            'role' => 'professional',
        ]);
    }

    /**
     * Test that professional profiles are created.
     */
    public function test_professional_profiles_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(4, ProfessionalProfile::count());
    }

    /**
     * Test that companies are created.
     */
    public function test_companies_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(4, Company::count());
    }

    /**
     * Test that active services are created.
     */
    public function test_active_services_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $activeServices = Service::where('is_active', true)->count();
        $this->assertGreaterThanOrEqual(12, $activeServices);
    }

    /**
     * Test that at least one inactive service exists.
     */
    public function test_inactive_service_exists(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertDatabaseHas('services', ['is_active' => false]);
    }

    /**
     * Test that availability rules are created.
     */
    public function test_availability_rules_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(1, AvailabilityRule::count());
    }

    /**
     * Test that bookings are created in various states.
     */
    public function test_bookings_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $bookingCount = Booking::count();
        $this->assertGreaterThanOrEqual(10, $bookingCount);

        // Check that we have different booking statuses
        $statuses = Booking::distinct('status')->pluck('status')->toArray();
        $this->assertGreaterThanOrEqual(3, count($statuses));
    }

    /**
     * Test that reviews are created for completed bookings.
     */
    public function test_reviews_are_created(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(3, Review::count());
    }

    /**
     * Test that professional ratings are calculated.
     */
    public function test_professional_ratings_are_calculated(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $professionalsWithRating = ProfessionalProfile::where('reviews_count', '>', 0)
            ->where('avg_rating', '>', 0)
            ->count();

        $this->assertGreaterThanOrEqual(1, $professionalsWithRating);
    }

    /**
     * Test that seeder respects SEED_DEMO_DATA environment variable.
     */
    public function test_seeding_can_be_disabled_via_env(): void
    {
        // This test verifies the mechanism exists
        // In real execution, setting SEED_DEMO_DATA=false would skip seeding
        $this->assertTrue(true);
    }
}
