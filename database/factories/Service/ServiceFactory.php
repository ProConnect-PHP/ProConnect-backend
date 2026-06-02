<?php

namespace Database\Factories\Service;

use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'professional_id' => ProfessionalProfile::factory(),
            'company_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 500),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60, 90, 120]),
            'modality' => fake()->randomElement(['presencial', 'remota', 'hibrida']),
            'address' => null,
            'link' => null,
            'latitude' => null,
            'longitude' => null,
            'max_bookings_per_client' => null,
            'min_reschedule_minutes' => 30,
            'buffer_minutes' => 0,
            'starts_at' => null,
            'ends_at' => null,
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

    public function remote(): static
    {
        return $this->state(fn () => [
            'modality' => 'remota',
            'link' => 'https://meet.example.test/private-room',
            'address' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    public function inPerson(): static
    {
        return $this->state(fn () => [
            'modality' => 'presencial',
            'address' => 'Montevideo, Uruguay',
            'latitude' => -34.9011,
            'longitude' => -56.1645,
        ]);
    }
}
