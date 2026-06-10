<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
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
