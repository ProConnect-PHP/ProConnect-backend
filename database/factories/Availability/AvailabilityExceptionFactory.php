<?php

namespace Database\Factories\Availability;

use App\Models\Availability\AvailabilityException;
use App\Models\Service\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityException>
 */
class AvailabilityExceptionFactory extends Factory
{
    protected $model = AvailabilityException::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'exception_date' => '2026-06-15',
            'is_unavailable' => true,
            'alt_start' => null,
            'alt_end' => null,
            'reason' => 'Feriado',
        ];
    }
}
