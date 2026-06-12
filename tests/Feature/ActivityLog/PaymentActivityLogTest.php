<?php

namespace Tests\Feature\ActivityLog;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Support\ActivityLog\ActivityLogEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PaymentActivityLogTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithActivityLogs;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->clearActivityLogs();
    }

    protected function tearDown(): void
    {
        $this->clearActivityLogs();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_payment_creation_creates_activity_log(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents", [
                'metadata' => ['source' => 'activity-test'],
            ])
            ->assertCreated();

        $log = $this->activityLog(ActivityLogEvent::PaymentCreated->value);

        $this->assertNotNull($log);
        $this->assertSame($response->json('payment_intent.id'), $log->entity_id);
        $this->assertSame($booking->id, $log->metadata['booking_id']);
        $this->assertArrayNotHasKey('metadata', $log->metadata);
    }

    public function test_package_purchase_creates_activity_log(): void
    {
        $client = User::factory()->create();
        $packageProduct = PackageProduct::factory()->active()->create([
            'sessions_count' => 4,
            'price' => 5600,
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/package-products/{$packageProduct->id}/purchase")
            ->assertOk();

        $log = $this->activityLog(ActivityLogEvent::PackagePurchased->value);

        $this->assertNotNull($log);
        $this->assertSame($response->json('client_package.id'), $log->entity_id);
        $this->assertSame($packageProduct->id, $log->metadata['package_product_id']);
        $this->assertSame(4, $log->metadata['sessions_total']);
        $this->assertSame(4, $log->metadata['sessions_remaining']);
    }

    private function confirmedBookingScenario(): array
    {
        $client = User::factory()->create();
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1800,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 10:00:00',
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);

        return [$client, $booking];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
