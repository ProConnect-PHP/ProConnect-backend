<?php

namespace Database\Factories\Package;

use App\Enums\Package\PackageSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageSession>
 */
class PackageSessionFactory extends Factory
{
    protected $model = PackageSession::class;

    public function definition(): array
    {
        return [
            'client_package_id' => ClientPackage::factory(),
            'booking_id' => Booking::factory(),
            'client_id' => User::factory(),
            'professional_id' => ProfessionalProfile::factory(),
            'consumed_at' => null,
            'released_at' => null,
            'status' => PackageSessionStatus::Reserved,
            'metadata' => null,
        ];
    }

    public function forClientPackage(ClientPackage $clientPackage): static
    {
        return $this->state(fn () => [
            'client_package_id' => $clientPackage->id,
            'client_id' => $clientPackage->client_id,
            'professional_id' => $clientPackage->professional_id,
        ]);
    }

    public function forBooking(Booking $booking): static
    {
        return $this->state(fn () => [
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'professional_id' => $booking->professional_id,
        ]);
    }

    public function reserved(): static
    {
        return $this->state(fn () => [
            'status' => PackageSessionStatus::Reserved,
            'consumed_at' => null,
            'released_at' => null,
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => [
            'status' => PackageSessionStatus::Consumed,
            'consumed_at' => now(),
            'released_at' => null,
        ]);
    }

    public function released(): static
    {
        return $this->state(fn () => [
            'status' => PackageSessionStatus::Released,
            'consumed_at' => null,
            'released_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => PackageSessionStatus::Cancelled,
        ]);
    }
}
