<?php

namespace Tests\Feature\Booking;

use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use App\Services\Booking\BookingCancellationPolicyChecker;
use App\Services\Booking\BookingReschedulingPolicyChecker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyCheckerTest extends TestCase
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

    public function test_client_can_cancel_outside_the_cutoff(): void
    {
        [$booking] = $this->createScenario([
            'starts_at' => now()->addMinutes(121),
            'ends_at' => now()->addMinutes(181),
        ]);

        app(BookingCancellationPolicyChecker::class)->assertClientCanCancel($booking);

        $this->addToAssertionCount(1);
    }

    public function test_client_cannot_cancel_inside_the_cutoff(): void
    {
        [$booking] = $this->createScenario([
            'starts_at' => now()->addMinutes(119),
            'ends_at' => now()->addMinutes(179),
        ]);

        $this->assertApiError(
            fn () => app(BookingCancellationPolicyChecker::class)
                ->assertClientCanCancel($booking),
            'CancellationWindowExpired'
        );
    }

    public function test_client_cannot_cancel_when_professional_disabled_it(): void
    {
        [$booking, $professional] = $this->createScenario();
        $professional->bookingPolicy()->update([
            'allow_client_cancellation' => false,
        ]);

        $this->assertApiError(
            fn () => app(BookingCancellationPolicyChecker::class)
                ->assertClientCanCancel($booking),
            'ClientCancellationDisabled'
        );
    }

    public function test_client_cannot_cancel_non_cancellable_statuses(): void
    {
        foreach ([
            BookingStatus::InProgress,
            BookingStatus::Completed,
            BookingStatus::Cancelled,
            BookingStatus::NoShow,
        ] as $status) {
            [$booking] = $this->createScenario([
                'status' => $status,
            ]);

            $this->assertApiError(
                fn () => app(BookingCancellationPolicyChecker::class)
                    ->assertClientCanCancel($booking),
                'InvalidBookingStatusTransition'
            );
        }
    }

    public function test_client_can_reschedule_outside_the_cutoff(): void
    {
        [$booking] = $this->createScenario([
            'starts_at' => now()->addMinutes(121),
            'ends_at' => now()->addMinutes(181),
        ]);

        app(BookingReschedulingPolicyChecker::class)->assertClientCanReschedule($booking);

        $this->addToAssertionCount(1);
    }

    public function test_client_cannot_reschedule_inside_the_cutoff(): void
    {
        [$booking] = $this->createScenario([
            'starts_at' => now()->addMinutes(119),
            'ends_at' => now()->addMinutes(179),
        ]);

        $this->assertApiError(
            fn () => app(BookingReschedulingPolicyChecker::class)
                ->assertClientCanReschedule($booking),
            'ReschedulingWindowExpired'
        );
    }

    public function test_client_cannot_reschedule_when_professional_disabled_it(): void
    {
        [$booking, $professional] = $this->createScenario();
        $professional->bookingPolicy()->update([
            'allow_client_rescheduling' => false,
        ]);

        $this->assertApiError(
            fn () => app(BookingReschedulingPolicyChecker::class)
                ->assertClientCanReschedule($booking),
            'ClientReschedulingDisabled'
        );
    }

    public function test_client_cannot_reschedule_non_reschedulable_statuses(): void
    {
        foreach ([
            BookingStatus::InProgress,
            BookingStatus::Completed,
            BookingStatus::Cancelled,
            BookingStatus::NoShow,
        ] as $status) {
            [$booking] = $this->createScenario([
                'status' => $status,
            ]);

            $this->assertApiError(
                fn () => app(BookingReschedulingPolicyChecker::class)
                    ->assertClientCanReschedule($booking),
                'InvalidBookingStatusTransition'
            );
        }
    }

    private function createScenario(array $bookingOverrides = []): array
    {
        $professionalUser = User::factory()->professional()->create();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $client = User::factory()->create();
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'duration_minutes' => 60,
        ]);
        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $professional->id,
            'client_id' => $client->id,
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(4),
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            ...$bookingOverrides,
        ]);

        return [$booking, $professional, $client];
    }

    private function assertApiError(callable $callback, string $error): void
    {
        try {
            $callback();
            $this->fail("Expected API error {$error}.");
        } catch (ApiException $exception) {
            $this->assertSame($error, $exception->error());
        }
    }
}
