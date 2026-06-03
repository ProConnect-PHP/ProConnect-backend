<?php

namespace Database\Seeders\Demo;

use App\Models\User\User;
use App\Models\Company\Company;
use Illuminate\Database\Seeder;

/**
 * Seeds demo companies/practices for professionals.
 *
 * Creates 1-2 companies per professional with realistic contact info.
 */
class DemoCompanySeeder extends Seeder
{
    private array $companies = [
        // Psychologist
        [
            'email' => 'psicologa@proconnect.test',
            'commercial_name' => 'Centro Bienestar Punta',
            'legal_name' => 'Centro Bienestar Punta del Este S.A.',
            'tax_id' => '12345678-9',
            'contact_info' => [
                'phone' => '+598 99 123 456',
                'website' => 'https://centrobienestarpunta.local',
            ],
            'is_private' => false,
        ],
        // Coach
        [
            'email' => 'coach@proconnect.test',
            'commercial_name' => 'Oficina Mentoría Sur',
            'legal_name' => 'Ferreira Coaching & Consulting',
            'tax_id' => '98765432-1',
            'contact_info' => [
                'phone' => '+598 99 234 567',
                'website' => 'https://mentoriasur.local',
            ],
            'is_private' => false,
        ],
        // Nutritionist
        [
            'email' => 'nutricionista@proconnect.test',
            'commercial_name' => 'NutriHábitos Uruguay',
            'legal_name' => 'NutriHábitos S.R.L.',
            'tax_id' => '55555555-5',
            'contact_info' => [
                'phone' => '+598 99 345 678',
                'website' => 'https://nutrihabitos.local',
            ],
            'is_private' => false,
        ],
        // Consultant
        [
            'email' => 'consultor@proconnect.test',
            'commercial_name' => 'Moreira Consulting Studio',
            'legal_name' => 'Santiago Moreira - Consultoría Empresarial',
            'tax_id' => '77777777-7',
            'contact_info' => [
                'phone' => '+598 99 456 789',
                'website' => 'https://moreiracons.local',
            ],
            'is_private' => false,
        ],
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        foreach ($this->companies as $companyData) {
            $user = User::where('email', $companyData['email'])->first();

            if ($user && $user->professionalProfile) {
                Company::updateOrCreate(
                    [
                        'professional_id' => $user->professionalProfile->id,
                        'commercial_name' => $companyData['commercial_name'],
                    ],
                    [
                        'legal_name' => $companyData['legal_name'],
                        'tax_id' => $companyData['tax_id'],
                        'contact_info' => $companyData['contact_info'],
                        'is_private' => $companyData['is_private'],
                    ]
                );
            }
        }

        $this->command?->info('✓ Demo companies created/updated');
    }
}
