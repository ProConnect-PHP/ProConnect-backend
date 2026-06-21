<?php

namespace Tests\Feature\Booking;

use App\Enums\Booking\BookingStatus;
use App\Models\Booking\Booking;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalAgendaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_agenda_supports_month_view(): void
    {
        [$professional] = $this->agendaScenario();

        $this->agenda($professional, 'view=month&date=2026-06-20')
            ->assertOk()
            ->assertJsonPath('view', 'month');
    }

    public function test_month_view_returns_the_start_and_end_of_the_selected_month(): void
    {
        [$professional] = $this->agendaScenario();

        $this->agenda($professional, 'view=month&date=2026-06-20')
            ->assertOk()
            ->assertJsonPath('range.from', '2026-06-01 00:00:00')
            ->assertJsonPath('range.to', '2026-06-30 23:59:59');
    }

    public function test_month_view_events_are_scoped_to_the_selected_month(): void
    {
        [$professional, $profile, $service, $client] = $this->agendaScenario();
        $mayBooking = $this->createBooking($service, $profile, $client, '2026-05-31 10:00:00');
        $juneBooking = $this->createBooking($service, $profile, $client, '2026-06-01 10:00:00');
        $juneEndBooking = $this->createBooking($service, $profile, $client, '2026-06-30 23:00:00');
        $julyBooking = $this->createBooking($service, $profile, $client, '2026-07-01 10:00:00');

        $response = $this->agenda($professional, 'view=month&date=2026-06-20')->assertOk();

        $response
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.id', $juneBooking->id)
            ->assertJsonPath('events.1.id', $juneEndBooking->id);

        $eventIds = collect($response->json('events'))->pluck('id')->all();

        $this->assertNotContains($mayBooking->id, $eventIds);
        $this->assertNotContains($julyBooking->id, $eventIds);
    }

    public function test_range_summary_counts_only_bookings_inside_the_visible_month(): void
    {
        [$professional, $profile, $service, $client] = $this->agendaScenario();
        $this->createBooking($service, $profile, $client, '2026-06-03 10:00:00', BookingStatus::Pending);
        $this->createBooking($service, $profile, $client, '2026-06-15 10:00:00', BookingStatus::Completed);
        $this->createBooking($service, $profile, $client, '2026-05-30 10:00:00', BookingStatus::Confirmed);

        $this->agenda($professional, 'view=month&date=2026-06-20')
            ->assertOk()
            ->assertJsonPath('range_summary.total', 2)
            ->assertJsonPath('range_summary.pending', 1)
            ->assertJsonPath('range_summary.completed', 1)
            ->assertJsonPath('range_summary.confirmed', 0);
    }

    public function test_global_summary_counts_all_professional_bookings_without_a_range_filter(): void
    {
        [$professional, $profile, $service, $client] = $this->agendaScenario();
        $this->createBooking($service, $profile, $client, '2026-05-30 10:00:00', BookingStatus::Confirmed);
        $this->createBooking($service, $profile, $client, '2026-06-15 10:00:00', BookingStatus::Completed);
        $this->createBooking($service, $profile, $client, '2026-07-01 10:00:00', BookingStatus::Cancelled);

        $this->agenda($professional, 'view=month&date=2026-06-20')
            ->assertOk()
            ->assertJsonPath('global_summary.total', 3)
            ->assertJsonPath('global_summary.confirmed', 1)
            ->assertJsonPath('global_summary.completed', 1)
            ->assertJsonPath('global_summary.cancelled', 1);
    }

    public function test_global_summary_excludes_bookings_from_other_professionals(): void
    {
        [$professional, $profile, $service, $client] = $this->agendaScenario();
        $this->createBooking($service, $profile, $client, '2026-06-15 10:00:00', BookingStatus::Confirmed);

        [, $otherProfile] = $this->createProfessional();
        $otherService = Service::factory()->create(['professional_id' => $otherProfile->id]);

        $this->createBooking($otherService, $otherProfile, $client, '2026-05-15 10:00:00', BookingStatus::Completed);
        $this->createBooking($otherService, $otherProfile, $client, '2026-06-15 10:00:00', BookingStatus::Completed);
        $this->createBooking($otherService, $otherProfile, $client, '2026-07-15 10:00:00', BookingStatus::Completed);

        $this->agenda($professional, 'view=month&date=2026-06-20')
            ->assertOk()
            ->assertJsonPath('global_summary.total', 1)
            ->assertJsonPath('global_summary.confirmed', 1)
            ->assertJsonPath('global_summary.completed', 0);
    }

    public function test_existing_week_range_behavior_remains_available(): void
    {
        [$professional, $profile, $service, $client] = $this->agendaScenario();
        $weekBooking = $this->createBooking($service, $profile, $client, '2026-06-15 10:00:00');
        $this->createBooking($service, $profile, $client, '2026-06-22 10:00:00');

        $this->agenda($professional, 'from=2026-06-15&to=2026-06-21')
            ->assertOk()
            ->assertJsonPath('view', 'week')
            ->assertJsonPath('range.from', '2026-06-15 00:00:00')
            ->assertJsonPath('range.to', '2026-06-21 23:59:59')
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $weekBooking->id);
    }

    public function test_summary_remains_a_backward_compatible_alias_for_range_summary(): void
    {
        [$professional, $profile, $service, $client] = $this->agendaScenario();
        $this->createBooking($service, $profile, $client, '2026-06-15 10:00:00', BookingStatus::Paid);

        $response = $this->agenda($professional, 'view=month&date=2026-06-20')->assertOk();

        $this->assertSame($response->json('range_summary'), $response->json('summary'));
    }

    private function agenda(User $professional, string $query)
    {
        return $this
            ->withHeaders($this->authHeaders($professional))
            ->getJson('/api/v1/professional/agenda?'.$query);
    }

    private function agendaScenario(): array
    {
        [$professional, $profile] = $this->createProfessional();
        $service = Service::factory()->create([
            'professional_id' => $profile->id,
            'duration_minutes' => 60,
        ]);

        return [$professional, $profile, $service, User::factory()->create()];
    }

    private function createBooking(
        Service $service,
        ProfessionalProfile $profile,
        User $client,
        string $startsAt,
        BookingStatus $status = BookingStatus::Pending,
    ): Booking {
        $startsAt = CarbonImmutable::parse($startsAt, config('app.timezone'));

        return Booking::factory()->create([
            'service_id' => $service->id,
            'professional_id' => $profile->id,
            'client_id' => $client->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($service->duration_minutes),
            'status' => $status,
            'modality' => $service->modality,
            'price_snapshot' => $service->price,
            'duration_minutes_snapshot' => $service->duration_minutes,
            'confirmed_at' => in_array($status, [BookingStatus::Confirmed, BookingStatus::Paid], true)
                ? $startsAt->subDay()
                : null,
            'paid_at' => $status === BookingStatus::Paid ? $startsAt->subDay() : null,
            'completed_at' => $status === BookingStatus::Completed ? $startsAt->addHour() : null,
            'cancelled_at' => $status === BookingStatus::Cancelled ? $startsAt->subHour() : null,
        ]);
    }

    private function createProfessional(): array
    {
        $professional = User::factory()->professional()->create();
        $profile = ProfessionalProfile::factory()->create([
            'user_id' => $professional->id,
        ]);

        return [$professional, $profile];
    }

    private function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.auth('user_jwt')->login($user),
            'Accept' => 'application/json',
        ];
    }
}
