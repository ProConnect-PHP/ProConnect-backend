<?php

namespace Tests\Feature\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalBookingPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_professional_can_view_default_policy_and_rules(): void
    {
        [$user] = $this->createProfessional();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/professional/me/booking-policy')
            ->assertOk()
            ->assertJsonPath('data.cancellation_cutoff_minutes', 120)
            ->assertJsonPath('data.reminders_enabled', true)
            ->assertJsonCount(2, 'data.reminder_rules');
    }

    public function test_professional_can_update_policy(): void
    {
        [$user] = $this->createProfessional();

        $this->withHeaders($this->authHeaders($user))
            ->putJson('/api/v1/professional/me/booking-policy', [
                'allow_client_cancellation' => false,
                'cancellation_cutoff_minutes' => 180,
                'allow_client_rescheduling' => true,
                'rescheduling_cutoff_minutes' => 60,
                'late_tolerance_minutes' => 15,
                'reminders_enabled' => false,
                'cancellation_policy_text' => 'No se permiten cancelaciones online.',
                'rescheduling_policy_text' => 'Puedes reprogramar hasta una hora antes.',
            ])
            ->assertOk()
            ->assertJsonPath('data.allow_client_cancellation', false)
            ->assertJsonPath('data.rescheduling_cutoff_minutes', 60)
            ->assertJsonPath('data.reminders_enabled', false);
    }

    public function test_client_cannot_update_professional_policy(): void
    {
        $client = User::factory()->create();

        $this->withHeaders($this->authHeaders($client))
            ->putJson('/api/v1/professional/me/booking-policy', [])
            ->assertForbidden();
    }

    public function test_professional_cannot_update_another_professionals_rule(): void
    {
        [$user] = $this->createProfessional();
        [, $otherProfessional] = $this->createProfessional();
        $rule = $otherProfessional->reminderRules()->firstOrFail();

        $this->withHeaders($this->authHeaders($user))
            ->putJson(
                "/api/v1/professional/me/reminder-rules/{$rule->id}",
                $this->rulePayload(30)
            )
            ->assertForbidden();
    }

    public function test_reminder_rule_requires_at_least_one_channel(): void
    {
        [$user] = $this->createProfessional();

        $this->withHeaders($this->authHeaders($user))
            ->postJson(
                '/api/v1/professional/me/reminder-rules',
                $this->rulePayload(30, [
                    'send_email' => false,
                    'send_database_notification' => false,
                ])
            )
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonPath(
                'error.details.channels.0',
                'Debe seleccionarse al menos un canal.'
            );
    }

    public function test_reminder_rule_requires_at_least_one_recipient(): void
    {
        [$user] = $this->createProfessional();

        $this->withHeaders($this->authHeaders($user))
            ->postJson(
                '/api/v1/professional/me/reminder-rules',
                $this->rulePayload(30, [
                    'notify_client' => false,
                    'notify_professional' => false,
                ])
            )
            ->assertUnprocessable()
            ->assertJsonPath(
                'error.details.recipients.0',
                'Debe seleccionarse al menos un destinatario.'
            );
    }

    public function test_professional_cannot_duplicate_rule_minutes(): void
    {
        [$user] = $this->createProfessional();

        $this->withHeaders($this->authHeaders($user))
            ->postJson(
                '/api/v1/professional/me/reminder-rules',
                $this->rulePayload(120)
            )
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError');
    }

    public function test_professional_can_create_update_and_delete_a_rule(): void
    {
        [$user] = $this->createProfessional();
        $headers = $this->authHeaders($user);

        $ruleId = $this->withHeaders($headers)
            ->postJson(
                '/api/v1/professional/me/reminder-rules',
                $this->rulePayload(30)
            )
            ->assertCreated()
            ->assertJsonPath('data.minutes_before_start', 30)
            ->json('data.id');

        $this->withHeaders($headers)
            ->putJson(
                "/api/v1/professional/me/reminder-rules/{$ruleId}",
                $this->rulePayload(45)
            )
            ->assertOk()
            ->assertJsonPath('data.minutes_before_start', 45);

        $this->withHeaders($headers)
            ->deleteJson("/api/v1/professional/me/reminder-rules/{$ruleId}")
            ->assertOk();

        $this->assertDatabaseMissing('professional_booking_reminder_rules', [
            'id' => $ruleId,
        ]);
    }

    public function test_deleted_default_rule_is_not_recreated_when_policy_is_read(): void
    {
        [$user, $professional] = $this->createProfessional();
        $rule = $professional->reminderRules()
            ->where('minutes_before_start', 1440)
            ->firstOrFail();

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/v1/professional/me/reminder-rules/{$rule->id}")
            ->assertOk();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/v1/professional/me/booking-policy')
            ->assertOk()
            ->assertJsonCount(1, 'data.reminder_rules');

        $this->assertDatabaseMissing('professional_booking_reminder_rules', [
            'id' => $rule->id,
        ]);
    }

    public function test_client_can_query_available_actions_without_bypassing_policy(): void
    {
        [, $professional] = $this->createProfessional();
        $professional->bookingPolicy()->update([
            'allow_client_cancellation' => false,
            'allow_client_rescheduling' => true,
            'rescheduling_cutoff_minutes' => 60,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'status' => BookingStatus::Confirmed,
        ]);

        $this->withHeaders($this->authHeaders($client))
            ->getJson("/api/v1/bookings/{$booking->id}/available-actions")
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.can_reschedule', true)
            ->assertJsonPath(
                'data.cancel_disabled_reason',
                'El profesional no permite cancelaciones online.'
            )
            ->assertJsonPath('data.reschedule_disabled_reason', null);
    }

    private function createProfessional(): array
    {
        $user = User::factory()->professional()->create();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $professional];
    }

    private function rulePayload(int $minutes, array $overrides = []): array
    {
        return [
            'minutes_before_start' => $minutes,
            'send_email' => true,
            'send_database_notification' => true,
            'send_push' => false,
            'send_whatsapp' => false,
            'notify_client' => true,
            'notify_professional' => false,
            'is_active' => true,
            ...$overrides,
        ];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
