<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Enums\Payment\PaymentProvider;
use App\Enums\Payment\PaymentStatus;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPaymentsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_lists_only_their_payments(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $payment = $this->createPaymentForClient($client);
        $otherPayment = $this->createPaymentForClient($otherClient);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $payment->id)
            ->assertJsonPath('payments.0.kind', 'payment')
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame($otherPayment->id, $response->json('payments.0.id'));
    }

    public function test_professional_lists_only_received_payments(): void
    {
        [$professionalUser, $profile] = $this->professionalWithProfile();
        [, $otherProfile] = $this->professionalWithProfile();
        $payment = $this->createPaymentForProfessional($profile);
        $otherPayment = $this->createPaymentForProfessional($otherProfile);

        $response = $this
            ->withHeaders($this->authHeaders($professionalUser))
            ->getJson('/api/v1/professional/payments');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $payment->id)
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame($otherPayment->id, $response->json('payments.0.id'));
    }

    public function test_user_without_professional_profile_cannot_list_professional_payments(): void
    {
        $client = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/professional/payments')
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_payments_are_paginated(): void
    {
        $client = User::factory()->create();
        $this->createPaymentForClient($client);
        $this->createPaymentForClient($client);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_client_lists_pending_intent_without_payment(): void
    {
        $client = User::factory()->create();
        $intent = $this->createIntentForClient(
            client: $client,
            status: PaymentIntentStatus::Pending,
        );

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my')
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $intent->id)
            ->assertJsonPath('payments.0.kind', 'payment_intent')
            ->assertJsonPath('payments.0.status', 'pending')
            ->assertJsonPath('payments.0.display_status', 'Pendiente')
            ->assertJsonPath('payments.0.is_final', false)
            ->assertJsonPath('payments.0.is_pending', true);
    }

    public function test_client_lists_paypal_checkout_intent_with_continuation_data(): void
    {
        $client = User::factory()->create();
        $intent = $this->createIntentForClient(
            client: $client,
            status: PaymentIntentStatus::CheckoutCreated,
            provider: PaymentProvider::PayPal,
            attributes: [
                'provider_reference' => '7HJ11981UA509071K',
                'checkout_url' => 'https://paypal.test/checkout/7HJ11981UA509071K',
                'metadata' => ['paypal_order_status' => 'CREATED'],
            ],
        );

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my')
            ->assertOk()
            ->assertJsonPath('payments.0.id', $intent->id)
            ->assertJsonPath('payments.0.kind', 'payment_intent')
            ->assertJsonPath('payments.0.status', 'checkout_created')
            ->assertJsonPath('payments.0.display_status', 'Checkout creado')
            ->assertJsonPath('payments.0.provider', 'paypal')
            ->assertJsonPath('payments.0.provider_reference', '7HJ11981UA509071K')
            ->assertJsonPath('payments.0.provider_status', 'CREATED')
            ->assertJsonPath('payments.0.can_continue_checkout', true)
            ->assertJsonPath('payments.0.payment_intent_id', $intent->id);
    }

    public function test_client_lists_failed_intent_without_payment(): void
    {
        $client = User::factory()->create();
        $intent = $this->createIntentForClient(
            client: $client,
            status: PaymentIntentStatus::Failed,
            attributes: ['failed_at' => now()],
        );

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my')
            ->assertOk()
            ->assertJsonPath('payments.0.id', $intent->id)
            ->assertJsonPath('payments.0.status', 'failed')
            ->assertJsonPath('payments.0.display_status', 'Fallido')
            ->assertJsonPath('payments.0.is_final', true)
            ->assertJsonPath('payments.0.can_retry', true);
    }

    public function test_successful_payment_is_prioritized_over_its_payment_intent(): void
    {
        $client = User::factory()->create();
        $payment = $this->createPaymentForClient($client);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $payment->id)
            ->assertJsonPath('payments.0.kind', 'payment')
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame(
            $payment->payment_intent_id,
            $response->json('payments.0.id')
        );
    }

    public function test_client_payment_movements_support_status_provider_and_kind_filters(): void
    {
        $client = User::factory()->create();
        $pendingPayPal = $this->createIntentForClient(
            client: $client,
            status: PaymentIntentStatus::Pending,
            provider: PaymentProvider::PayPal,
        );
        $this->createIntentForClient(
            client: $client,
            status: PaymentIntentStatus::Failed,
            provider: PaymentProvider::MercadoPago,
            attributes: ['failed_at' => now()],
        );
        $this->createPaymentForClient($client);

        $headers = $this->authHeaders($client);

        $this->withHeaders($headers)
            ->getJson('/api/v1/payments/my?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $pendingPayPal->id);

        $this->withHeaders($headers)
            ->getJson('/api/v1/payments/my?provider=paypal')
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.provider', 'paypal');

        $this->withHeaders($headers)
            ->getJson('/api/v1/payments/my?kind=payment_intent&only_pending=1')
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $pendingPayPal->id)
            ->assertJsonPath('payments.0.kind', 'payment_intent');
    }

    public function test_client_payment_movements_mix_sources_in_descending_relevant_date_order(): void
    {
        $client = User::factory()->create();
        $payment = $this->createPaymentForClient($client);
        $payment->update([
            'paid_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);
        $intent = $this->createIntentForClient(
            client: $client,
            status: PaymentIntentStatus::Processing,
            attributes: [
                'updated_at' => now(),
                'created_at' => now()->subHours(2),
            ],
        );

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my?per_page=1&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.id', $intent->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('payments.0.id', $payment->id);
    }

    public function test_per_page_cannot_exceed_fifty(): void
    {
        $client = User::factory()->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my?per_page=51')
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    private function createPaymentForClient(User $client): Payment
    {
        $professional = ProfessionalProfile::factory()->create();

        return $this->createPayment($client, $professional);
    }

    private function createPaymentForProfessional(ProfessionalProfile $professional): Payment
    {
        $client = User::factory()->create();

        return $this->createPayment($client, $professional);
    }

    private function createPayment(User $client, ProfessionalProfile $professional): Payment
    {
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1500,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Paid,
            'confirmed_at' => now()->subDay(),
            'paid_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->succeeded()
            ->create([
                'status' => PaymentIntentStatus::Succeeded,
            ]);

        return Payment::factory()
            ->forPaymentIntent($intent)
            ->succeeded()
            ->create([
                'status' => PaymentStatus::Succeeded,
            ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createIntentForClient(
        User $client,
        PaymentIntentStatus $status,
        PaymentProvider $provider = PaymentProvider::Simulator,
        array $attributes = [],
    ): PaymentIntent {
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1500,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
        ]);

        return PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'provider' => $provider,
                'status' => $status,
                ...$attributes,
            ]);
    }

    private function professionalWithProfile(): array
    {
        $user = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    private function authHeaders(User $user): array
    {
        $token = auth('user_jwt')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
