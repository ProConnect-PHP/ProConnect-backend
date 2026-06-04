<?php

namespace Tests\Feature\Package;

use App\Actions\Package\ConsumePackageSessionAction;
use App\Actions\Package\ReleasePackageSessionAction;
use App\Enums\Booking\BookingStatus;
use App\Enums\Package\ClientPackageStatus;
use App\Enums\Package\PackageSessionStatus;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use App\Models\Service\Service;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_decrements_used_sessions_and_reactivates_depleted_package(): void
    {
        [$booking, $clientPackage, $session] = $this->reservedSessionScenario([
            'status' => ClientPackageStatus::Depleted,
            'total_sessions' => 1,
            'used_sessions' => 1,
            'depleted_at' => now(),
        ]);

        app(ReleasePackageSessionAction::class)($booking);

        $clientPackage->refresh();
        $session->refresh();

        $this->assertSame(0, $clientPackage->used_sessions);
        $this->assertSame(ClientPackageStatus::Active, $clientPackage->status);
        $this->assertNull($clientPackage->depleted_at);
        $this->assertSame(PackageSessionStatus::Released, $session->status);
        $this->assertNotNull($session->released_at);
    }

    public function test_consuming_reserved_session_marks_it_consumed_without_changing_used_sessions(): void
    {
        [$booking, $clientPackage, $session] = $this->reservedSessionScenario([
            'used_sessions' => 1,
        ]);

        app(ConsumePackageSessionAction::class)($booking);

        $this->assertSame(1, $clientPackage->refresh()->used_sessions);
        $this->assertSame(PackageSessionStatus::Consumed, $session->refresh()->status);
        $this->assertNotNull($session->consumed_at);
    }

    public function test_consumed_session_is_not_released(): void
    {
        [$booking, $clientPackage, $session] = $this->reservedSessionScenario([
            'used_sessions' => 1,
        ]);
        $session->update([
            'status' => PackageSessionStatus::Consumed,
            'consumed_at' => now(),
        ]);

        app(ReleasePackageSessionAction::class)($booking);

        $this->assertSame(1, $clientPackage->refresh()->used_sessions);
        $this->assertSame(PackageSessionStatus::Consumed, $session->refresh()->status);
    }

    private function reservedSessionScenario(array $packageOverrides = []): array
    {
        $client = User::factory()->create();
        $service = Service::factory()->create();
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $service->professional_id,
            'client_id' => $client->id,
            'status' => BookingStatus::Pending,
        ]);
        $clientPackage = ClientPackage::factory()
            ->forService($service)
            ->active()
            ->create([
                'client_id' => $client->id,
                ...$packageOverrides,
            ]);
        $booking->update([
            'client_package_id' => $clientPackage->id,
        ]);
        $session = PackageSession::factory()
            ->forClientPackage($clientPackage)
            ->forBooking($booking)
            ->reserved()
            ->create();

        return [$booking, $clientPackage, $session];
    }
}
