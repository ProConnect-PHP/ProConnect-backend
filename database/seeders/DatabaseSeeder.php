<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoDatabaseSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Only runs demo seeding in local/testing environments to protect production.
     */
    public function run(): void
    {
        // Protect production from accidental demo data
        if (app()->environment('production')) {
            return;
        }

        // Allow disabling demo seeding via environment variable
        if (! (bool) env('SEED_DEMO_DATA', true)) {
            return;
        }

        // Load demo dataset
        $this->call(DemoDatabaseSeeder::class);
    }
}
