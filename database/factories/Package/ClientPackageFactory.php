<?php

namespace Database\Factories\Package;

use App\Enums\Package\ClientPackageStatus;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPackage>
 */
class ClientPackageFactory extends Factory
{
    protected $model = ClientPackage::class;

    public function definition(): array
    {
        return [
            'package_product_id' => PackageProduct::factory(),
            'client_id' => User::factory(),
            'professional_id' => ProfessionalProfile::factory(),
            'service_id' => null,
            'status' => ClientPackageStatus::Active,
            'total_sessions' => 4,
            'used_sessions' => 0,
            'price_snapshot' => 5600,
            'currency' => config('proconnect.payments.currency', 'UYU'),
            'purchased_at' => now(),
            'expires_at' => now()->addDays(60),
            'cancelled_at' => null,
            'depleted_at' => null,
            'metadata' => null,
        ];
    }

    public function forPackageProduct(PackageProduct $packageProduct): static
    {
        return $this->state(fn () => [
            'package_product_id' => $packageProduct->id,
            'professional_id' => $packageProduct->professional_id,
            'service_id' => $packageProduct->service_id,
            'total_sessions' => $packageProduct->sessions_count,
            'price_snapshot' => $packageProduct->price,
            'currency' => $packageProduct->currency,
            'expires_at' => $packageProduct->validity_days
                ? now()->addDays($packageProduct->validity_days)
                : null,
        ]);
    }

    public function forService(Service $service): static
    {
        return $this->state(fn () => [
            'professional_id' => $service->professional_id,
            'service_id' => $service->id,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => ClientPackageStatus::Active,
            'cancelled_at' => null,
            'depleted_at' => null,
        ]);
    }

    public function depleted(): static
    {
        return $this->state(fn () => [
            'status' => ClientPackageStatus::Depleted,
            'used_sessions' => 4,
            'depleted_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ClientPackageStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ClientPackageStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function withRemainingSessions(int $remaining): static
    {
        return $this->state(function () use ($remaining) {
            $total = max($remaining, 1);

            return [
                'total_sessions' => $total,
                'used_sessions' => max(0, $total - $remaining),
                'status' => $remaining > 0
                    ? ClientPackageStatus::Active
                    : ClientPackageStatus::Depleted,
                'depleted_at' => $remaining > 0 ? null : now(),
            ];
        });
    }
}
