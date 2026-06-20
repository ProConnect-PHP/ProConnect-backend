<?php

namespace Tests\Feature\Payment;

use App\Enums\Booking\BookingStatus;
use App\Enums\Payment\PaymentIntentStatus;
use App\Models\Booking\Booking;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentIntent;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_lists_only_their_real_payments(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        [$payment, $booking] = $this->paymentFor($client);
        [$otherPayment] = $this->paymentFor($otherClient);
        PaymentIntent::factory()->forBooking($booking)->failed()->create();

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id)
            ->assertJsonPath('data.0.payment_intent_id', $payment->payment_intent_id)
            ->assertJsonPath('data.0.booking.id', $booking->id)
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame($otherPayment->id, $response->json('data.0.id'));
    }

    public function test_client_cannot_view_another_clients_payment(): void
    {
        $client = User::factory()->create();
        [$payment] = $this->paymentFor(User::factory()->create());

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson("/api/v1/me/payments/{$payment->id}")
            ->assertForbidden()
            ->assertJsonPath('error.type', 'Forbidden');
    }

    public function test_client_can_view_payment_detail_with_related_attempts(): void
    {
        $client = User::factory()->create();
        [$payment, $booking, $successfulIntent] = $this->paymentFor($client);
        $olderAttempt = PaymentIntent::factory()
            ->forBooking($booking)
            ->failed()
            ->create(['created_at' => now()->subHours(2)]);
        $latestAttempt = PaymentIntent::factory()
            ->forBooking($booking)
            ->expired()
            ->create(['created_at' => now()->addMinute()]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson("/api/v1/me/payments/{$payment->id}")
            ->assertOk()
            ->assertJsonPath('payment.id', $payment->id)
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonPath('successful_intent.id', $successfulIntent->id)
            ->assertJsonCount(3, 'related_attempts')
            ->assertJsonPath('related_attempts.0.id', $latestAttempt->id)
            ->assertJsonPath('related_attempts.2.id', $olderAttempt->id);
    }

    public function test_cancelled_booking_cannot_create_a_payment_intent(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Cancelled);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/bookings/{$booking->id}/payment-intents")
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingNotPayable')
            ->assertJsonPath(
                'error.message',
                'Esta reserva no puede pagarse en su estado actual.'
            );
    }

    public function test_cancelled_booking_cannot_continue_an_existing_checkout(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Cancelled);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'status' => PaymentIntentStatus::CheckoutCreated,
                'checkout_url' => 'https://checkout.example.test/payment',
            ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->postJson("/api/v1/payment-intents/{$intent->id}/checkout", [
                'provider' => 'simulator',
            ])
            ->assertConflict()
            ->assertJsonPath('error.type', 'BookingNotPayable')
            ->assertJsonPath(
                'error.message',
                'Esta reserva no puede pagarse en su estado actual.'
            );
    }

    public function test_cancelled_booking_intent_exposes_no_checkout_continuation_action(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Cancelled);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'status' => PaymentIntentStatus::CheckoutCreated,
                'checkout_url' => 'https://checkout.example.test/payment',
            ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/payments/my')
            ->assertOk()
            ->assertJsonPath('payments.0.id', $intent->id)
            ->assertJsonPath('payments.0.can_continue_checkout', false)
            ->assertJsonPath('payments.0.checkout_url', null);
    }

    /** @return array{0: Payment, 1: Booking, 2: PaymentIntent} */
    private function paymentFor(User $client): array
    {
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);
        $intent = PaymentIntent::factory()->forBooking($booking)->succeeded()->create();
        $payment = Payment::factory()->forPaymentIntent($intent)->succeeded()->create();

        return [$payment, $booking, $intent];
    }

    private function bookingFor(User $client, BookingStatus $status): Booking
    {
        $professional = ProfessionalProfile::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'price' => 1500,
            'duration_minutes' => 60,
            'modality' => 'remota',
        ]);

        return Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'status' => $status,
            'confirmed_at' => $status === BookingStatus::Confirmed ? now() : null,
            'cancelled_at' => $status === BookingStatus::Cancelled ? now() : null,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            'modality' => $service->modality,
        ]);
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
