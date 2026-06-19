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
        $this->call(AdminUserSeeder::class);
        $this->call(DemoDatabaseSeeder::class);
    }
}
