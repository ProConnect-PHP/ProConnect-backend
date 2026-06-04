<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;

/**
 * Master seeder for demo/development dataset.
 *
 * Orchestrates all demo seeders in proper order:
 * 1. Users (3 clients, 4 professionals = 7 total)
 * 2. Professional Profiles
 * 3. Companies
 * 4. Services
 * 5. Availability Rules
 * 6. Availability Exceptions
 * 7. Bookings
 * 8. Packages
 * 9. Payments
 * 10. Reviews
 * 11. Review Replies
 */
class DemoDatabaseSeeder extends Seeder
{
    /**
     * Run the demo database seeders.
     */
    public function run(): void
    {
        // Call seeders in dependency order
        $this->call([
            DemoUserSeeder::class,
            DemoProfessionalSeeder::class,
            DemoCompanySeeder::class,
            DemoServiceSeeder::class,
            DemoAvailabilitySeeder::class,
            DemoBookingSeeder::class,
            DemoPackageSeeder::class,
            DemoPaymentSeeder::class,
            DemoReviewSeeder::class,
        ]);
    }
}
