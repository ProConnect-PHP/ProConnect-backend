<?php

namespace Tests\Feature\Seeder;

use App\Enums\Booking\BookingStatus;
use App\Enums\Package\ClientPackageStatus;
use App\Enums\Package\PackageSessionStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentStatus;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Package\PackageSession;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use Database\Seeders\Demo\DemoDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoPackagesAndPaymentsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_packages_and_payments_are_seeded_consistently(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(8, PackageProduct::query()->count());
        $this->assertGreaterThanOrEqual(7, PackageProduct::query()->where('is_active', true)->count());
        $this->assertGreaterThanOrEqual(1, PackageProduct::query()->where('is_active', false)->count());

        $this->assertGreaterThanOrEqual(4, ClientPackage::query()->count());
        $this->assertGreaterThanOrEqual(1, ClientPackage::query()->where('status', ClientPackageStatus::Active->value)->count());
        $this->assertGreaterThanOrEqual(1, ClientPackage::query()->where('status', ClientPackageStatus::Depleted->value)->count());
        $this->assertGreaterThanOrEqual(1, ClientPackage::query()->where('status', ClientPackageStatus::Expired->value)->count());

        $this->assertGreaterThanOrEqual(3, PackageSession::query()->count());
        $this->assertGreaterThanOrEqual(1, PackageSession::query()->where('status', PackageSessionStatus::Reserved->value)->count());
        $this->assertGreaterThanOrEqual(1, PackageSession::query()->where('status', PackageSessionStatus::Consumed->value)->count());
        $this->assertGreaterThanOrEqual(1, PackageSession::query()->where('status', PackageSessionStatus::Released->value)->count());

        $this->assertGreaterThanOrEqual(
            1,
            PackageSession::query()
                ->whereHas('booking', fn ($query) => $query->whereNotNull('client_package_id'))
                ->count()
        );

        $this->assertGreaterThanOrEqual(1, PaymentIntent::query()->where('status', PaymentIntentStatus::Succeeded->value)->count());
        $this->assertGreaterThanOrEqual(1, PaymentIntent::query()->where('status', PaymentIntentStatus::Pending->value)->count());
        $this->assertGreaterThanOrEqual(1, PaymentIntent::query()->where('status', PaymentIntentStatus::Failed->value)->count());
        $this->assertGreaterThanOrEqual(1, Payment::query()->where('status', PaymentStatus::Succeeded->value)->count());

        Payment::query()->with('booking')->each(function (Payment $payment): void {
            $this->assertSame(BookingStatus::Paid, $payment->booking->status);
            $this->assertNull($payment->booking->client_package_id);
        });

        $this->assertSame(
            0,
            Payment::query()
                ->whereHas('booking', fn ($query) => $query->whereNotNull('client_package_id'))
                ->count()
        );

        ClientPackage::query()->with('sessions')->each(function (ClientPackage $clientPackage): void {
            $countedSessions = $clientPackage->sessions
                ->whereIn('status', [
                    PackageSessionStatus::Reserved,
                    PackageSessionStatus::Consumed,
                ])
                ->count();

            $this->assertSame($clientPackage->used_sessions, $countedSessions);
        });
    }
}
