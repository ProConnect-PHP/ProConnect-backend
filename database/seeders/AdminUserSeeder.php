<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@proconnect.test'],
            [
                'name' => 'Admin ProConnect',
                'password' => Hash::make('Password123!'),
                'role' => UserRole::Admin,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
    }
}
