<?php

namespace Tests\Feature\Public;

use App\Models\Availability\AvailabilityException;
use App\Models\Availability\AvailabilityRule;
use App\Models\Company\Company;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServicesApiTest extends TestCase
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

    public function test_lists_only_active_services(): void
    {
        $profile = $this->createProfessionalProfile();

        $active = $this->createService($profile, [
            'name' => 'Active service',
            'is_active' => true,
        ]);
        $inactive = $this->createService($profile, [
            'name' => 'Inactive service',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/public/services');

        $response
            ->assertOk()
            ->assertJsonPath('services.0.id', $active->id);

        $this->assertContains($active->name, $this->serviceNames($response));
        $this->assertNotContains($inactive->name, $this->serviceNames($response));
    }

    public function test_index_does_not_require_authentication(): void
    {
        $profile = $this->createProfessionalProfile();
        $service = $this->createService($profile);

        $response = $this->getJson('/api/v1/public/services');

        $response
            ->assertOk()
            ->assertJsonPath('services.0.id', $service->id);
    }

    public function test_filters_by_search_in_service_name(): void
    {
        $profile = $this->createProfessionalProfile();
        $consulting = $this->createService($profile, [
            'name' => 'Consultoria Inicial',
        ]);
        $piano = $this->createService($profile, [
            'name' => 'Clase de Piano',
        ]);

        $response = $this->getJson('/api/v1/public/services?search=consultoria');

        $response->assertOk();

        $this->assertContains($consulting->name, $this->serviceNames($response));
        $this->assertNotContains($piano->name, $this->serviceNames($response));
    }

    public function test_filters_by_search_in_professional_name(): void
    {
        $anaProfile = $this->createProfessionalProfile(userOverrides: [
            'name' => 'Ana Coach',
        ]);
        $otherProfile = $this->createProfessionalProfile(userOverrides: [
            'name' => 'Luis Mentor',
        ]);

        $anaService = $this->createService($anaProfile, [
            'name' => 'Coaching profesional',
        ]);
        $otherService = $this->createService($otherProfile, [
            'name' => 'Mentoria tecnica',
        ]);

        $response = $this->getJson('/api/v1/public/services?search=Ana');

        $response->assertOk();

        $this->assertContains($anaService->name, $this->serviceNames($response));
        $this->assertNotContains($otherService->name, $this->serviceNames($response));
    }

    public function test_filters_by_modality(): void
    {
        $profile = $this->createProfessionalProfile();

        $remote = $this->createService($profile, [
            'name' => 'Remote service',
            'modality' => 'remota',
        ]);
        $inPerson = $this->createService($profile, [
            'name' => 'In person service',
            'modality' => 'presencial',
        ]);

        $response = $this->getJson('/api/v1/public/services?modality=remota');

        $response->assertOk();

        $this->assertContains($remote->name, $this->serviceNames($response));
        $this->assertNotContains($inPerson->name, $this->serviceNames($response));
    }

    public function test_filters_by_minimum_and_maximum_price(): void
    {
        $profile = $this->createProfessionalProfile();

        $inside = $this->createService($profile, [
            'name' => 'Inside range',
            'price' => 1500,
        ]);
        $cheap = $this->createService($profile, [
            'name' => 'Too cheap',
            'price' => 500,
        ]);
        $expensive = $this->createService($profile, [
            'name' => 'Too expensive',
            'price' => 3000,
        ]);

        $response = $this->getJson('/api/v1/public/services?min_price=1000&max_price=2000');

        $response->assertOk();

        $this->assertContains($inside->name, $this->serviceNames($response));
        $this->assertNotContains($cheap->name, $this->serviceNames($response));
        $this->assertNotContains($expensive->name, $this->serviceNames($response));
    }

    public function test_filters_by_duration_minutes(): void
    {
        $profile = $this->createProfessionalProfile();

        $sixty = $this->createService($profile, [
            'name' => 'Sixty minutes',
            'duration_minutes' => 60,
        ]);
        $ninety = $this->createService($profile, [
            'name' => 'Ninety minutes',
            'duration_minutes' => 90,
        ]);

        $response = $this->getJson('/api/v1/public/services?duration_minutes=60');

        $response->assertOk();

        $this->assertContains($sixty->name, $this->serviceNames($response));
        $this->assertNotContains($ninety->name, $this->serviceNames($response));
    }

    public function test_filters_by_verified_professional(): void
    {
        $verifiedProfile = $this->createProfessionalProfile(profileOverrides: [
            'is_verified' => true,
        ]);
        $unverifiedProfile = $this->createProfessionalProfile(profileOverrides: [
            'is_verified' => false,
        ]);

        $verifiedService = $this->createService($verifiedProfile, [
            'name' => 'Verified service',
        ]);
        $unverifiedService = $this->createService($unverifiedProfile, [
            'name' => 'Unverified service',
        ]);

        $response = $this->getJson('/api/v1/public/services?is_verified=true');

        $response->assertOk();

        $this->assertContains($verifiedService->name, $this->serviceNames($response));
        $this->assertNotContains($unverifiedService->name, $this->serviceNames($response));
    }

    public function test_filters_by_available_date_with_active_rule(): void
    {
        $profile = $this->createProfessionalProfile();
        $available = $this->createService($profile, [
            'name' => 'Monday service',
        ]);
        $unavailable = $this->createService($profile, [
            'name' => 'No Monday service',
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $available->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);
        AvailabilityRule::factory()->create([
            'service_id' => $unavailable->id,
            'day_of_week' => 2,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/public/services?available_date=2026-06-15');

        $response->assertOk();

        $this->assertContains($available->name, $this->serviceNames($response));
        $this->assertNotContains($unavailable->name, $this->serviceNames($response));
    }

    public function test_available_date_excludes_unavailable_exception(): void
    {
        $profile = $this->createProfessionalProfile();
        $service = $this->createService($profile, [
            'name' => 'Blocked Monday service',
        ]);

        AvailabilityRule::factory()->create([
            'service_id' => $service->id,
            'day_of_week' => 1,
            'is_active' => true,
        ]);
        AvailabilityException::factory()->create([
            'service_id' => $service->id,
            'exception_date' => '2026-06-15',
            'is_unavailable' => true,
        ]);

        $response = $this->getJson('/api/v1/public/services?available_date=2026-06-15');

        $response
            ->assertOk()
            ->assertJsonCount(0, 'services');
    }

    public function test_paginates_public_services(): void
    {
        $profile = $this->createProfessionalProfile();

        Service::factory()->count(15)->create([
            'professional_id' => $profile->id,
        ]);

        $response = $this->getJson('/api/v1/public/services?per_page=10');

        $response
            ->assertOk()
            ->assertJsonCount(10, 'services')
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_invalid_modality_returns_validation_error(): void
    {
        $response = $this->getJson('/api/v1/public/services?modality=invalid');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['modality'],
                ],
            ]);
    }

    public function test_incomplete_geo_filter_returns_validation_error(): void
    {
        $response = $this->getJson('/api/v1/public/services?latitude=-34.9');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.type', 'ValidationError')
            ->assertJsonStructure([
                'error' => [
                    'details' => ['longitude', 'radius_km'],
                ],
            ]);
    }

    public function test_filters_by_geographic_radius(): void
    {
        $profile = $this->createProfessionalProfile();

        $montevideo = $this->createService($profile, [
            'name' => 'Montevideo service',
            'modality' => 'presencial',
            'address' => 'Montevideo, Uruguay',
            'latitude' => -34.9011,
            'longitude' => -56.1645,
        ]);
        $buenosAires = $this->createService($profile, [
            'name' => 'Buenos Aires service',
            'modality' => 'presencial',
            'address' => 'Buenos Aires, Argentina',
            'latitude' => -34.6037,
            'longitude' => -58.3816,
        ]);

        $response = $this->getJson(
            '/api/v1/public/services?latitude=-34.9011&longitude=-56.1645&radius_km=20'
        );

        $response->assertOk();

        $this->assertContains($montevideo->name, $this->serviceNames($response));
        $this->assertNotContains($buenosAires->name, $this->serviceNames($response));
        $this->assertIsNumeric($response->json('services.0.distance_km'));
    }

    public function test_sorts_by_price_ascending(): void
    {
        $profile = $this->createProfessionalProfile();

        $expensive = $this->createService($profile, [
            'name' => 'Expensive service',
            'price' => 300,
        ]);
        $cheap = $this->createService($profile, [
            'name' => 'Cheap service',
            'price' => 100,
        ]);

        $response = $this->getJson('/api/v1/public/services?sort=price_asc');

        $response
            ->assertOk()
            ->assertJsonPath('services.0.id', $cheap->id)
            ->assertJsonPath('services.1.id', $expensive->id);
    }

    public function test_does_not_list_services_outside_public_date_window(): void
    {
        $profile = $this->createProfessionalProfile();

        $current = $this->createService($profile, [
            'name' => 'Current service',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-30',
        ]);
        $future = $this->createService($profile, [
            'name' => 'Future service',
            'starts_at' => '2026-06-10',
        ]);
        $ended = $this->createService($profile, [
            'name' => 'Ended service',
            'ends_at' => '2026-05-31',
        ]);

        $response = $this->getJson('/api/v1/public/services');

        $response->assertOk();

        $this->assertContains($current->name, $this->serviceNames($response));
        $this->assertNotContains($future->name, $this->serviceNames($response));
        $this->assertNotContains($ended->name, $this->serviceNames($response));
    }

    public function test_show_returns_public_active_service(): void
    {
        $profile = $this->createProfessionalProfile(userOverrides: [
            'name' => 'Jose Hernandez',
        ]);
        $service = $this->createService($profile, [
            'name' => 'Consultoria Inicial',
        ]);

        $response = $this->getJson("/api/v1/public/services/{$service->id}");

        $response
            ->assertOk()
            ->assertJsonPath('service.id', $service->id)
            ->assertJsonPath('service.professional.id', $profile->id)
            ->assertJsonPath('service.professional.user.name', 'Jose Hernandez');
    }

    public function test_show_inactive_service_returns_404(): void
    {
        $profile = $this->createProfessionalProfile();
        $service = $this->createService($profile, [
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/public/services/{$service->id}");

        $response
            ->assertNotFound()
            ->assertJsonPath('error.type', 'NotFound');
    }

    public function test_show_does_not_expose_private_remote_link(): void
    {
        $profile = $this->createProfessionalProfile();
        $service = Service::factory()
            ->remote()
            ->create([
                'professional_id' => $profile->id,
                'name' => 'Remote strategy session',
            ]);

        $response = $this->getJson("/api/v1/public/services/{$service->id}");

        $response
            ->assertOk()
            ->assertJsonMissingPath('service.link');
    }

    public function test_show_does_not_expose_private_company(): void
    {
        $profile = $this->createProfessionalProfile();
        $company = Company::factory()->create([
            'professional_id' => $profile->id,
            'commercial_name' => 'Private Studio',
            'is_private' => true,
        ]);
        $service = $this->createService($profile, [
            'company_id' => $company->id,
        ]);

        $response = $this->getJson("/api/v1/public/services/{$service->id}");

        $response
            ->assertOk()
            ->assertJsonPath('service.company', null)
            ->assertJsonMissingPath('service.company.legal_name')
            ->assertJsonMissingPath('service.company.tax_id');
    }

    private function createProfessionalProfile(
        array $profileOverrides = [],
        array $userOverrides = []
    ): ProfessionalProfile {
        $user = User::factory()->professional()->create($userOverrides);

        return ProfessionalProfile::factory()->create([
            'user_id' => $user->id,
            ...$profileOverrides,
        ]);
    }

    private function createService(ProfessionalProfile $profile, array $overrides = []): Service
    {
        return Service::factory()->create([
            'professional_id' => $profile->id,
            ...$overrides,
        ]);
    }

    private function serviceNames($response): array
    {
        return collect($response->json('services'))
            ->pluck('name')
            ->all();
    }
}
