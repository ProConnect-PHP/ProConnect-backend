<?php

namespace Database\Seeders\Demo;

use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds demo users for all roles:

 * - 3 clients
 * - 4 professionals
 *
 * All demo users have password: password123
 */
class DemoUserSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password123';

    private array $demoUsers = [
        // Clients
        [
            'name' => 'Cliente Demo',
            'email' => 'cliente@proconnect.test',
            'role' => 'client',
        ],
        [
            'name' => 'Cliente Secundario',
            'email' => 'cliente2@proconnect.test',
            'role' => 'client',
        ],
        [
            'name' => 'Cliente Tercero',
            'email' => 'cliente3@proconnect.test',
            'role' => 'client',
        ],
        // Professionals
        [
            'name' => 'Dra. Valentina Acosta',
            'email' => 'psicologa@proconnect.test',
            'role' => 'professional',
        ],
        [
            'name' => 'Mateo Ferreira',
            'email' => 'coach@proconnect.test',
            'role' => 'professional',
        ],
        [
            'name' => 'Lucía Benítez',
            'email' => 'nutricionista@proconnect.test',
            'role' => 'professional',
        ],
        [
            'name' => 'Santiago Moreira',
            'email' => 'consultor@proconnect.test',
            'role' => 'professional',
        ],
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        foreach ($this->demoUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => self::DEMO_PASSWORD,
                    'email_verified_at' => now(),
                ])
            );
        }

        $this->command?->info('✓ Demo users created/updated');
    }
}
