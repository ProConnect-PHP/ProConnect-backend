<?php

namespace Database\Seeders\Demo;

use App\Models\User\User;
use App\Models\Service\Service;
use Illuminate\Database\Seeder;

/**
 * Seeds demo services for professionals.
 *
 * Creates 12+ services with various modalities:
 * - 3 services for Psychologist (presencial, remota, hibrida)
 * - 3 services for Coach (remote, hybrid, one-time)
 * - 3 services for Nutritionist (in-person, remote, hybrid)
 * - 3 services for Consultant (various pricing/duration)
 * - 1 inactive service for testing
 *
 * Includes realistic pricing, durations, addresses, and coordinates.
 */
class DemoServiceSeeder extends Seeder
{
    private array $services = [
        // ===== PSYCHOLOGIST (psicologa@proconnect.test) =====
        [
            'email' => 'psicologa@proconnect.test',
            'name' => 'Consulta psicológica inicial',
            'description' => 'Primera sesión de evaluación y diagnóstico. Incluye anamnesis completa, identificación de objetivos terapéuticos y propuesta de tratamiento.',
            'modality' => 'presencial',
            'price' => 1800,
            'duration_minutes' => 60,
            'address' => 'Av. Roosevelt 2350 y Parada 10, Punta del Este, Uruguay',
            'latitude' => -34.9369,
            'longitude' => -54.9347,
            'is_active' => true,
        ],
        [
            'email' => 'psicologa@proconnect.test',
            'name' => 'Terapia online individual',
            'description' => 'Sesión de terapia individualizada por videollamada. Acceso desde cualquier lugar, flexible y confidencial.',
            'modality' => 'remota',
            'price' => 1600,
            'duration_minutes' => 50,
            'link' => null,
            'is_active' => true,
        ],
        [
            'email' => 'psicologa@proconnect.test',
            'name' => 'Acompañamiento para ansiedad',
            'description' => 'Programa especializado de 4 sesiones para manejo de ansiedad. Técnicas cognitivo-conductuales, meditación guiada y herramientas prácticas.',
            'modality' => 'hibrida',
            'price' => 1900,
            'duration_minutes' => 60,
            'address' => 'Av. Roosevelt 2350 y Parada 10, Punta del Este, Uruguay',
            'latitude' => -34.9369,
            'longitude' => -54.9347,
            'is_active' => true,
        ],

        // ===== COACH (coach@proconnect.test) =====
        [
            'email' => 'coach@proconnect.test',
            'name' => 'Sesión de coaching ejecutivo',
            'description' => 'Sesión individual de coaching ejecutivo enfocada en liderazgo, toma de decisiones y optimización de desempeño.',
            'modality' => 'remota',
            'price' => 2200,
            'duration_minutes' => 60,
            'link' => null,
            'is_active' => true,
        ],
        [
            'email' => 'coach@proconnect.test',
            'name' => 'Mentoría de productividad',
            'description' => 'Sesión de mentoría enfocada en sistemas de productividad, gestión del tiempo y hábitos de alto rendimiento.',
            'modality' => 'hibrida',
            'price' => 2000,
            'duration_minutes' => 45,
            'address' => 'Centro, Maldonado, Uruguay',
            'latitude' => -34.9087,
            'longitude' => -54.9587,
            'is_active' => true,
        ],
        [
            'email' => 'coach@proconnect.test',
            'name' => 'Plan de objetivos trimestral',
            'description' => 'Sesión intensiva de planificación trimestral. Define objetivos SMART, métricas de progreso y acciones concretas para los próximos 90 días.',
            'modality' => 'remota',
            'price' => 3500,
            'duration_minutes' => 90,
            'link' => null,
            'is_active' => true,
        ],

        // ===== NUTRITIONIST (nutricionista@proconnect.test) =====
        [
            'email' => 'nutricionista@proconnect.test',
            'name' => 'Consulta nutricional inicial',
            'description' => 'Evaluación nutricional completa. Incluye análisis de hábitos, metas de salud, recomendaciones iniciales y propuesta de plan personalizado.',
            'modality' => 'presencial',
            'price' => 1700,
            'duration_minutes' => 60,
            'address' => 'Bvar. Artigas 1234, Montevideo, Uruguay',
            'latitude' => -34.9011,
            'longitude' => -56.1645,
            'is_active' => true,
        ],
        [
            'email' => 'nutricionista@proconnect.test',
            'name' => 'Seguimiento nutricional online',
            'description' => 'Sesión de seguimiento breve por videollamada. Revisión de adherencia al plan, ajustes necesarios y motivación para objetivos.',
            'modality' => 'remota',
            'price' => 1200,
            'duration_minutes' => 30,
            'link' => null,
            'is_active' => true,
        ],
        [
            'email' => 'nutricionista@proconnect.test',
            'name' => 'Plan alimentario personalizado',
            'description' => 'Elaboración de plan nutricional completo adaptado a tus objetivos, restricciones y preferencias. Incluye menú semanal y lista de compras.',
            'modality' => 'hibrida',
            'price' => 2500,
            'duration_minutes' => 75,
            'address' => 'Bvar. Artigas 1234, Montevideo, Uruguay',
            'latitude' => -34.9011,
            'longitude' => -56.1645,
            'is_active' => true,
        ],

        // ===== CONSULTANT (consultor@proconnect.test) =====
        [
            'email' => 'consultor@proconnect.test',
            'name' => 'Diagnóstico de negocio',
            'description' => 'Análisis estratégico de tu negocio actual. Evaluación de mercado, competencia, recursos y propuesta de mejoras.',
            'modality' => 'remota',
            'price' => 3000,
            'duration_minutes' => 60,
            'link' => null,
            'is_active' => true,
        ],
        [
            'email' => 'consultor@proconnect.test',
            'name' => 'Consultoría para emprendimientos',
            'description' => 'Asesoramiento integral para emprendedores. Desde validación de idea hasta Go-to-Market, financiamiento y sostenibilidad operacional.',
            'modality' => 'hibrida',
            'price' => 2800,
            'duration_minutes' => 60,
            'address' => 'Centro, Maldonado, Uruguay',
            'latitude' => -34.9087,
            'longitude' => -54.9587,
            'is_active' => true,
        ],
        [
            'email' => 'consultor@proconnect.test',
            'name' => 'Revisión de estrategia comercial',
            'description' => 'Análisis profundo de tu estrategia comercial actual. Reposicionamiento, optimización de canales y plan de crecimiento para los próximos 12-24 meses.',
            'modality' => 'remota',
            'price' => 3200,
            'duration_minutes' => 90,
            'link' => null,
            'is_active' => true,
        ],

        // ===== INACTIVE SERVICE FOR TESTING =====
        [
            'email' => 'psicologa@proconnect.test',
            'name' => 'Terapia de grupo (cerrada)',
            'description' => 'Este servicio está temporalmente no disponible.',
            'modality' => 'presencial',
            'price' => 800,
            'duration_minutes' => 90,
            'address' => 'Av. Roosevelt 2350 y Parada 10, Punta del Este, Uruguay',
            'latitude' => -34.9369,
            'longitude' => -54.9347,
            'is_active' => false,
        ],
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        foreach ($this->services as $serviceData) {
            $user = User::where('email', $serviceData['email'])->first();

            if ($user && $user->professionalProfile) {
                $company = $user->professionalProfile->companies()->first();

                Service::updateOrCreate(
                    [
                        'professional_id' => $user->professionalProfile->id,
                        'name' => $serviceData['name'],
                    ],
                    [
                        'company_id' => $company?->id,
                        'description' => $serviceData['description'],
                        'modality' => $serviceData['modality'],
                        'price' => $serviceData['price'],
                        'duration_minutes' => $serviceData['duration_minutes'],
                        'address' => $serviceData['address'] ?? null,
                        'latitude' => $serviceData['latitude'] ?? null,
                        'longitude' => $serviceData['longitude'] ?? null,
                        'link' => $serviceData['link'] ?? null,
                        'max_bookings_per_client' => 3,
                        'min_reschedule_minutes' => 120,
                        'buffer_minutes' => 15,
                        'is_active' => $serviceData['is_active'],
                    ]
                );
            }
        }

        $this->command?->info('✓ Demo services created/updated');
    }
}
