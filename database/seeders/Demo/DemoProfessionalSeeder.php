<?php

namespace Database\Seeders\Demo;

use App\Models\User\User;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Seeder;

/**
 * Seeds professional profiles for professional users.
 *
 * Creates bios and initial stats for:
 * - Psychologist
 * - Executive Coach
 * - Nutritionist
 * - Business Consultant
 */
class DemoProfessionalSeeder extends Seeder
{
    private array $professionals = [
        [
            'email' => 'psicologa@proconnect.test',
            'bio' => 'Psicóloga clínica especializada en ansiedad, estrés laboral y acompañamiento emocional. Más de 8 años de experiencia en terapia individual y grupal.',
            'is_verified' => true,
        ],
        [
            'email' => 'coach@proconnect.test',
            'bio' => 'Coach ejecutivo orientado a productividad, liderazgo y toma de decisiones. Especialización en transformación organizacional y desarrollo de equipos.',
            'is_verified' => true,
        ],
        [
            'email' => 'nutricionista@proconnect.test',
            'bio' => 'Nutricionista enfocada en hábitos sostenibles, planificación alimentaria y salud integral. Expertise en nutrición deportiva y bienestar corporativo.',
            'is_verified' => true,
        ],
        [
            'email' => 'consultor@proconnect.test',
            'bio' => 'Consultor de negocios para emprendedores y pequeñas empresas. Especialización en estrategia comercial, modelos de negocio e implementación de mejoras operacionales.',
            'is_verified' => false,
        ],
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        foreach ($this->professionals as $professionalData) {
            $user = User::where('email', $professionalData['email'])->first();

            if ($user) {
                ProfessionalProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'bio' => $professionalData['bio'],
                        'is_verified' => $professionalData['is_verified'],
                        'avg_rating' => 0,
                        'reviews_count' => 0,
                    ]
                );
            }
        }

        $this->command?->info('✓ Professional profiles created/updated');
    }
}
