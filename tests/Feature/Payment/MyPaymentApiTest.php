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

    public function test_client_lists_successful_payments_as_paid_history_items(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        [$payment, $booking] = $this->paymentFor($client);
        [$otherPayment] = $this->paymentFor($otherClient);

        $response = $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'payment:'.$payment->id)
            ->assertJsonPath('data.0.source', 'payment')
            ->assertJsonPath('data.0.payment_id', $payment->id)
            ->assertJsonPath('data.0.payment_intent_id', $payment->payment_intent_id)
            ->assertJsonPath('data.0.status', 'succeeded')
            ->assertJsonPath('data.0.display_status', 'paid')
            ->assertJsonPath('data.0.booking.id', $booking->id)
            ->assertJsonPath('meta.total', 1);

        $this->assertNotSame('payment:'.$otherPayment->id, $response->json('data.0.id'));
    }

    public function test_client_lists_rejected_intent_without_payment(): void
    {
        $this->withoutExceptionHandling();
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->rejected()
            ->create(['provider_reference' => 'mp_rejected_123']);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'intent:'.$intent->id)
            ->assertJsonPath('data.0.source', 'payment_intent')
            ->assertJsonPath('data.0.payment_id', null)
            ->assertJsonPath('data.0.payment_intent_id', $intent->id)
            ->assertJsonPath('data.0.status', 'rejected')
            ->assertJsonPath('data.0.display_status', 'rejected')
            ->assertJsonPath('data.0.can_retry', true);
    }

    public function test_client_lists_checkout_created_intent_as_not_confirmed(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'status' => PaymentIntentStatus::CheckoutCreated,
                'provider_reference' => 'mp_checkout_123',
                'checkout_url' => 'https://checkout.example.test/mp_checkout_123',
                'failure_reason' => null,
            ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'intent:'.$intent->id)
            ->assertJsonPath('data.0.status', 'checkout_created')
            ->assertJsonPath('data.0.display_status', 'not_confirmed')
            ->assertJsonPath(
                'data.0.failure_reason',
                'No encontramos un pago asociado a este intento.'
            )
            ->assertJsonPath('data.0.can_retry', true);
    }

    public function test_client_does_not_list_internal_pending_intent_without_provider_evidence(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);

        PaymentIntent::factory()
            ->forBooking($booking)
            ->pending()
            ->create([
                'provider_reference' => null,
                'checkout_url' => null,
                'metadata' => null,
            ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_client_does_not_duplicate_an_intent_that_already_has_a_payment(): void
    {
        $client = User::factory()->create();
        [$payment] = $this->paymentFor($client);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'payment:'.$payment->id)
            ->assertJsonPath('data.0.source', 'payment')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_cancelled_booking_history_item_cannot_be_retried(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Cancelled);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->failed()
            ->create(['provider_reference' => 'mp_failed_cancelled_booking']);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'intent:'.$intent->id)
            ->assertJsonPath('data.0.can_retry', false);
    }

    public function test_failed_intent_for_payable_booking_can_be_retried(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->failed()
            ->create(['provider_reference' => 'mp_failed_payable_booking']);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'intent:'.$intent->id)
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.can_retry', true);
    }

    public function test_intent_cannot_be_retried_when_its_payable_already_has_a_successful_payment(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);
        $failedIntent = PaymentIntent::factory()
            ->forBooking($booking)
            ->failed()
            ->create(['provider_reference' => 'mp_failed_before_success']);
        $successfulIntent = PaymentIntent::factory()
            ->forBooking($booking)
            ->succeeded()
            ->create();

        Payment::factory()
            ->forPaymentIntent($successfulIntent)
            ->succeeded()
            ->create();

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments/intent:'.$failedIntent->id)
            ->assertOk()
            ->assertJsonPath('item.can_retry', false);
    }

    public function test_history_is_ordered_by_created_at_descending(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);
        $olderIntent = PaymentIntent::factory()
            ->forBooking($booking)
            ->failed()
            ->create([
                'provider_reference' => 'mp_older_attempt',
                'created_at' => now()->subHour(),
            ]);
        $newerIntent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'status' => PaymentIntentStatus::CheckoutCreated,
                'provider_reference' => 'mp_newer_attempt',
                'checkout_url' => 'https://checkout.example.test/mp_newer_attempt',
                'created_at' => now(),
            ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'intent:'.$newerIntent->id)
            ->assertJsonPath('data.1.id', 'intent:'.$olderIntent->id);
    }

    public function test_client_cannot_see_another_clients_history_items(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $otherBooking = $this->bookingFor($otherClient, BookingStatus::Confirmed);
        $otherIntent = PaymentIntent::factory()
            ->forBooking($otherBooking)
            ->failed()
            ->create(['provider_reference' => 'mp_other_client']);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments/intent:'.$otherIntent->id)
            ->assertNotFound();
    }

    public function test_client_can_view_payment_history_detail_with_related_attempts(): void
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
            ->getJson('/api/v1/me/payments/payment:'.$payment->id)
            ->assertOk()
            ->assertJsonPath('item.id', 'payment:'.$payment->id)
            ->assertJsonPath('item.source', 'payment')
            ->assertJsonPath('item.payment_id', $payment->id)
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonCount(3, 'related_attempts')
            ->assertJsonPath('related_attempts.0.id', $latestAttempt->id)
            ->assertJsonPath('related_attempts.1.id', $successfulIntent->id)
            ->assertJsonPath('related_attempts.2.id', $olderAttempt->id);
    }

    public function test_client_can_view_intent_history_detail_without_payment(): void
    {
        $client = User::factory()->create();
        $booking = $this->bookingFor($client, BookingStatus::Confirmed);
        $intent = PaymentIntent::factory()
            ->forBooking($booking)
            ->create([
                'status' => PaymentIntentStatus::CheckoutCreated,
                'provider_reference' => 'mp_detail_without_payment',
                'checkout_url' => 'https://checkout.example.test/mp_detail_without_payment',
            ]);

        $this
            ->withHeaders($this->authHeaders($client))
            ->getJson('/api/v1/me/payments/intent:'.$intent->id)
            ->assertOk()
            ->assertJsonPath('item.id', 'intent:'.$intent->id)
            ->assertJsonPath('item.source', 'payment_intent')
            ->assertJsonPath('item.payment_id', null)
            ->assertJsonPath('item.payment_intent_id', $intent->id)
            ->assertJsonPath('item.display_status', 'not_confirmed')
            ->assertJsonPath('booking.id', $booking->id)
            ->assertJsonPath('related_attempts.0.id', $intent->id);
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

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
