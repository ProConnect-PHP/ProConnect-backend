<?php

namespace Database\Seeders\Demo;

use App\Enums\Booking\BookingStatus;
use App\Enums\Package\ClientPackageStatus;
use App\Enums\Package\PackageSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Package\PackageSession;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class DemoPackageSeeder extends Seeder
{
    public function run(): void
    {
        $psicologa = $this->professionalByEmail('psicologa@proconnect.test');
        $coach = $this->professionalByEmail('coach@proconnect.test');
        $nutricionista = $this->professionalByEmail('nutricionista@proconnect.test');
        $consultor = $this->professionalByEmail('consultor@proconnect.test');

        $products = [
            'therapy_4' => $this->upsertPackageProduct(
                $psicologa,
                $this->serviceByName($psicologa, 'Terapia online individual'),
                [
                    'name' => 'Pack 4 sesiones de terapia online',
                    'description' => 'Ideal para sostener un proceso terapeutico mensual por videollamada.',
                    'sessions_count' => 4,
                    'price' => 5600,
                    'validity_days' => 60,
                ]
            ),
            'anxiety_8' => $this->upsertPackageProduct(
                $psicologa,
                $this->serviceByName($psicologa, 'ansiedad'),
                [
                    'name' => 'Pack 8 sesiones de acompañamiento terapéutico',
                    'description' => 'Programa extendido para trabajar ansiedad con seguimiento continuo.',
                    'sessions_count' => 8,
                    'price' => 11200,
                    'validity_days' => 90,
                ]
            ),
            'coaching_4' => $this->upsertPackageProduct(
                $coach,
                $this->serviceByName($coach, 'coaching ejecutivo'),
                [
                    'name' => 'Pack 4 sesiones de coaching ejecutivo',
                    'description' => 'Cuatro sesiones para trabajar liderazgo, foco y toma de decisiones.',
                    'sessions_count' => 4,
                    'price' => 7800,
                    'validity_days' => 60,
                ]
            ),
            'productivity_6' => $this->upsertPackageProduct(
                $coach,
                $this->serviceByName($coach, 'objetivos trimestral'),
                [
                    'name' => 'Pack trimestral de productividad',
                    'description' => 'Acompañamiento durante un trimestre para ordenar objetivos y métricas.',
                    'sessions_count' => 6,
                    'price' => 18000,
                    'validity_days' => 120,
                ]
            ),
            'nutrition_3' => $this->upsertPackageProduct(
                $nutricionista,
                $this->serviceByName($nutricionista, 'Consulta nutricional inicial'),
                [
                    'name' => 'Pack 3 consultas nutricionales',
                    'description' => 'Evaluacion inicial, seguimiento y ajuste de plan nutricional.',
                    'sessions_count' => 3,
                    'price' => 4200,
                    'validity_days' => 45,
                ]
            ),
            'nutrition_followup_4' => $this->upsertPackageProduct(
                $nutricionista,
                $this->serviceByName($nutricionista, 'Seguimiento nutricional online'),
                [
                    'name' => 'Seguimiento nutricional mensual',
                    'description' => 'Cuatro controles online para ajustar habitos y progreso.',
                    'sessions_count' => 4,
                    'price' => 4000,
                    'validity_days' => 60,
                ]
            ),
            'business_5' => $this->upsertPackageProduct(
                $consultor,
                $this->serviceByName($consultor, 'emprendimientos'),
                [
                    'name' => 'Pack 5 mentorías de negocio',
                    'description' => 'Mentorías para validar, lanzar y mejorar un emprendimiento.',
                    'sessions_count' => 5,
                    'price' => 12500,
                    'validity_days' => 90,
                ]
            ),
            'diagnostic_3' => $this->upsertPackageProduct(
                $consultor,
                $this->serviceByName($consultor, 'negocio'),
                [
                    'name' => 'Pack diagnóstico + estrategia',
                    'description' => 'Tres sesiones para diagnóstico inicial y definición estratégica.',
                    'sessions_count' => 3,
                    'price' => 8100,
                    'validity_days' => 45,
                    'is_active' => false,
                ]
            ),
        ];

        $cliente = $this->userByEmail('cliente@proconnect.test');
        $cliente2 = $this->userByEmail('cliente2@proconnect.test');
        $cliente3 = $this->userByEmail('cliente3@proconnect.test');

        $therapyPackage = $this->upsertClientPackage($products['therapy_4'], $cliente, [
            'demo_key' => 'cliente_therapy_active',
            'status' => ClientPackageStatus::Active,
            'used_sessions' => 1,
            'purchased_at' => now()->subDays(10)->setTime(9, 0),
        ]);

        $coachPackage = $this->upsertClientPackage($products['coaching_4'], $cliente2, [
            'demo_key' => 'cliente2_coaching_active',
            'status' => ClientPackageStatus::Active,
            'used_sessions' => 2,
            'purchased_at' => now()->subDays(20)->setTime(9, 0),
        ]);

        $nutritionDepletedPackage = $this->upsertClientPackage($products['nutrition_3'], $cliente3, [
            'demo_key' => 'cliente3_nutrition_depleted',
            'status' => ClientPackageStatus::Depleted,
            'used_sessions' => 3,
            'purchased_at' => now()->subDays(40)->setTime(9, 0),
            'depleted_at' => now()->subDays(5)->setTime(12, 0),
        ]);

        $nutritionExpiredPackage = $this->upsertClientPackage($products['nutrition_followup_4'], $cliente3, [
            'demo_key' => 'cliente3_nutrition_expired',
            'status' => ClientPackageStatus::Expired,
            'used_sessions' => 1,
            'purchased_at' => now()->subDays(100)->setTime(9, 0),
            'expires_at' => now()->subDays(40)->setTime(23, 59),
        ]);

        $this->upsertPackageBooking(
            $cliente,
            $products['therapy_4']->service,
            $therapyPackage,
            now()->addDays(3)->setTime(10, 0),
            BookingStatus::Confirmed,
            PackageSessionStatus::Reserved,
            'cliente_therapy_reserved'
        );

        $this->upsertPackageBooking(
            $cliente2,
            $products['coaching_4']->service,
            $coachPackage,
            now()->subDays(7)->setTime(11, 0),
            BookingStatus::Completed,
            PackageSessionStatus::Consumed,
            'cliente2_coaching_consumed'
        );

        $this->upsertPackageBooking(
            $cliente2,
            $products['coaching_4']->service,
            $coachPackage,
            now()->addDays(4)->setTime(11, 0),
            BookingStatus::Confirmed,
            PackageSessionStatus::Reserved,
            'cliente2_coaching_reserved'
        );

        $this->upsertPackageBooking(
            $cliente2,
            $products['coaching_4']->service,
            $coachPackage,
            now()->subDays(3)->setTime(15, 0),
            BookingStatus::Cancelled,
            PackageSessionStatus::Released,
            'cliente2_coaching_released'
        );

        foreach ([30, 18, 5] as $index => $daysAgo) {
            $this->upsertPackageBooking(
                $cliente3,
                $products['nutrition_3']->service,
                $nutritionDepletedPackage,
                now()->subDays($daysAgo)->setTime(10 + $index, 0),
                BookingStatus::Completed,
                PackageSessionStatus::Consumed,
                "cliente3_nutrition_consumed_{$index}"
            );
        }

        $this->upsertPackageBooking(
            $cliente3,
            $products['nutrition_followup_4']->service,
            $nutritionExpiredPackage,
            now()->subDays(80)->setTime(9, 0),
            BookingStatus::Completed,
            PackageSessionStatus::Consumed,
            'cliente3_nutrition_expired_consumed'
        );

        $this->command?->info('Demo packages created/updated (8 products, 4 client packages)');
    }

    private function userByEmail(string $email): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw new RuntimeException("Demo user not found: {$email}");
        }

        return $user;
    }

    private function professionalByEmail(string $email): ProfessionalProfile
    {
        $profile = $this->userByEmail($email)->professionalProfile;

        if (! $profile) {
            throw new RuntimeException("Demo professional profile not found: {$email}");
        }

        return $profile;
    }

    private function serviceByName(ProfessionalProfile $professional, string $name): Service
    {
        $service = $professional->services()
            ->where('name', $name)
            ->first()
            ?? $professional->services()
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($name) . '%'])
                ->first();

        if (! $service) {
            throw new RuntimeException("Demo service not found for {$professional->id}: {$name}");
        }

        return $service;
    }

    private function upsertPackageProduct(
        ProfessionalProfile $professional,
        ?Service $service,
        array $data
    ): PackageProduct {
        $packageProduct = PackageProduct::query()
            ->withTrashed()
            ->firstOrNew([
                'professional_id' => $professional->id,
                'name' => $data['name'],
            ]);

        $packageProduct->fill([
            'service_id' => $service?->id,
            'description' => $data['description'] ?? null,
            'sessions_count' => $data['sessions_count'],
            'price' => $data['price'],
            'currency' => $data['currency'] ?? config('proconnect.payments.currency', 'UYU'),
            'validity_days' => $data['validity_days'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $packageProduct->save();

        if ($packageProduct->trashed()) {
            $packageProduct->restore();
        }

        return $packageProduct->refresh();
    }

    private function upsertClientPackage(
        PackageProduct $product,
        User $client,
        array $overrides = []
    ): ClientPackage {
        $purchasedAt = $overrides['purchased_at'] ?? now()->subDays(10)->setTime(9, 0);
        $expiresAt = array_key_exists('expires_at', $overrides)
            ? $overrides['expires_at']
            : ($product->validity_days ? $purchasedAt->copy()->addDays($product->validity_days) : null);

        return ClientPackage::query()->updateOrCreate(
            [
                'package_product_id' => $product->id,
                'client_id' => $client->id,
            ],
            [
                'professional_id' => $product->professional_id,
                'service_id' => $product->service_id,
                'status' => $overrides['status'] ?? ClientPackageStatus::Active,
                'total_sessions' => $overrides['total_sessions'] ?? $product->sessions_count,
                'used_sessions' => $overrides['used_sessions'] ?? 0,
                'price_snapshot' => $product->price,
                'currency' => $product->currency,
                'purchased_at' => $purchasedAt,
                'expires_at' => $expiresAt,
                'depleted_at' => $overrides['depleted_at'] ?? null,
                'cancelled_at' => $overrides['cancelled_at'] ?? null,
                'metadata' => [
                    'seeded' => true,
                    'purchase_mode' => 'simulated',
                    'demo_key' => $overrides['demo_key'] ?? null,
                ],
            ]
        );
    }

    private function upsertPackageBooking(
        User $client,
        Service $service,
        ClientPackage $clientPackage,
        Carbon $startsAt,
        BookingStatus $status,
        PackageSessionStatus $sessionStatus,
        string $demoKey
    ): Booking {
        $session = PackageSession::query()
            ->where('client_package_id', $clientPackage->id)
            ->where('metadata->demo_key', $demoKey)
            ->first();

        $booking = $session?->booking
            ?? Booking::query()
                ->where('service_id', $service->id)
                ->where('client_id', $client->id)
                ->where('starts_at', $startsAt)
                ->first()
            ?? new Booking();

        $endsAt = $startsAt->copy()->addMinutes($service->duration_minutes);
        $cancelledAt = $status === BookingStatus::Cancelled
            ? $startsAt->copy()->subDay()
            : null;

        $booking->fill([
            'service_id' => $service->id,
            'professional_id' => $service->professional_id,
            'client_id' => $client->id,
            'client_package_id' => $clientPackage->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            'confirmed_at' => in_array($status, [
                BookingStatus::Confirmed,
                BookingStatus::Completed,
                BookingStatus::Cancelled,
            ], true) ? $startsAt->copy()->subDays(2) : null,
            'paid_at' => null,
            'completed_at' => $status === BookingStatus::Completed ? $endsAt : null,
            'cancelled_at' => $cancelledAt,
            'cancellation_reason' => $status === BookingStatus::Cancelled ? 'Liberacion demo de sesion de paquete' : null,
            'reschedule_reason' => null,
        ]);
        $booking->save();

        PackageSession::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
            ],
            [
                'client_package_id' => $clientPackage->id,
                'client_id' => $client->id,
                'professional_id' => $service->professional_id,
                'status' => $sessionStatus,
                'consumed_at' => $sessionStatus === PackageSessionStatus::Consumed ? $endsAt : null,
                'released_at' => $sessionStatus === PackageSessionStatus::Released ? $cancelledAt : null,
                'metadata' => [
                    'seeded' => true,
                    'demo_key' => $demoKey,
                ],
            ]
        );

        return $booking;
    }
}
