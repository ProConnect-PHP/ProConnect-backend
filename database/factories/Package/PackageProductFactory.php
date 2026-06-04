<?php

namespace Database\Factories\Package;

use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageProduct>
 */
class PackageProductFactory extends Factory
{
    protected $model = PackageProduct::class;

    public function definition(): array
    {
        return [
            'professional_id' => ProfessionalProfile::factory(),
            'service_id' => null,
            'name' => 'Pack '.fake()->numberBetween(3, 8).' sesiones',
            'description' => fake()->paragraph(),
            'sessions_count' => fake()->numberBetween(3, 8),
            'price' => fake()->numberBetween(3000, 12000),
            'currency' => config('proconnect.payments.currency', 'UYU'),
            'validity_days' => fake()->numberBetween(30, 120),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function forService(Service $service): static
    {
        return $this->state(fn () => [
            'professional_id' => $service->professional_id,
            'service_id' => $service->id,
        ]);
    }

    public function sessions(int $count): static
    {
        return $this->state(fn () => [
            'sessions_count' => $count,
        ]);
    }

    public function price(int $price): static
    {
        return $this->state(fn () => [
            'price' => $price,
        ]);
    }
}
