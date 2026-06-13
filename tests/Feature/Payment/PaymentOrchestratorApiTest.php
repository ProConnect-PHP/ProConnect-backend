<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PayableType;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Events\Package\PackagePurchased;
use App\Events\Payment\PaymentSucceeded;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PaymentOrchestratorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_trust_frontend_amount_for_booking(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario(1800);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson('/api/v1/payment-intents', [
                'payable_type' => PayableType::Booking->value,
                'payable_id' => $booking->id,
                'provider' => PaymentProvider::Simulator->value,
                'amount' => 1,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('payment_intent.amount', 1800)
            ->assertJsonPath('payment_intent.payable_type', 'booking');
    }

    public function test_it_creates_payment_intent_for_package(): void
    {
        $client = User::factory()->create();
        $package = PackageProduct::factory()->active()->create([
            'price' => 5600,
            'currency' => 'UYU',
        ]);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson('/api/v1/payment-intents', [
                'payable_type' => PayableType::Package->value,
                'payable_id' => $package->id,
                'provider' => PaymentProvider::Simulator->value,
                'amount' => 10,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('payment_intent.package_product_id', $package->id)
            ->assertJsonPath('payment_intent.amount', 5600)
            ->assertJsonPath('payment_intent.currency', 'UYU');
    }

    public function test_it_creates_checkout_with_simulator_provider(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();
        $intentId = $this->createIntent($client, 'booking', $booking->id);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intentId}/checkout", [
                'provider' => PaymentProvider::Simulator->value,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath(
                'payment_intent.status',
                PaymentIntentStatus::CheckoutCreated->value
            )
            ->assertJsonPath('payment_intent.provider', 'simulator');
        $this->assertStringEndsWith(
            "/payments/simulator/{$intentId}",
            $response->json('payment_intent.checkout_url')
        );
    }

    public function test_successful_package_payment_creates_client_package_once(): void
    {
        Event::fake([PaymentSucceeded::class, PackagePurchased::class]);
        $client = User::factory()->create();
        $package = PackageProduct::factory()->active()->create([
            'sessions_count' => 4,
            'price' => 5600,
            'validity_days' => 60,
        ]);
        $intentId = $this->createIntent($client, 'package', $package->id);

        $first = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intentId}/simulate-success");
        $second = $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intentId}/simulate-success");

        $first
            ->assertOk()
            ->assertJsonPath('payment.package_product_id', $package->id);
        $second
            ->assertOk()
            ->assertJsonPath('payment.id', $first->json('payment.id'));

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('client_packages', 1);
        $this->assertDatabaseHas('client_packages', [
            'package_product_id' => $package->id,
            'client_id' => $client->id,
            'total_sessions' => 4,
            'used_sessions' => 0,
            'price_snapshot' => 5600,
        ]);
    }

    public function test_it_rejects_invalid_provider(): void
    {
        [$client, $booking] = $this->confirmedBookingScenario();

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson('/api/v1/payment-intents', [
                'payable_type' => 'booking',
                'payable_id' => $booking->id,
                'provider' => 'stripe',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    private function createIntent(
        User $client,
        string $payableType,
        string $payableId
    ): string {
        return $this
            ->withHeaders($this->authHeaders($client))
            ->postJson('/api/v1/payment-intents', [
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'provider' => 'simulator',
            ])
            ->assertCreated()
            ->json('payment_intent.id');
    }

    private function confirmedBookingScenario(int $price = 1500): array
    {
        $client = User::factory()->create();
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => $price,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'price_snapshot' => $price,
            'duration_minutes_snapshot' => 60,
            'modality' => 'remota',
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
