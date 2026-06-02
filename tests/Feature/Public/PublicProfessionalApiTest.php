<?php

namespace Tests\Feature\Public;

use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicProfessionalApiTest extends TestCase
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

    public function test_show_public_professional(): void
    {
        $profile = $this->createProfessionalProfile(
            profileOverrides: [
                'bio' => 'Coach profesional.',
                'avg_rating' => 4.8,
                'reviews_count' => 12,
                'is_verified' => true,
            ],
            userOverrides: [
                'name' => 'Jose Hernandez',
            ]
        );

        $response = $this->getJson("/api/v1/public/professionals/{$profile->id}");

        $response
            ->assertOk()
            ->assertJsonPath('professional.id', $profile->id)
            ->assertJsonPath('professional.bio', 'Coach profesional.')
            ->assertJsonPath('professional.avg_rating', 4.8)
            ->assertJsonPath('professional.reviews_count', 12)
            ->assertJsonPath('professional.is_verified', true)
            ->assertJsonPath('professional.user.name', 'Jose Hernandez');
    }

    public function test_show_includes_only_active_public_services(): void
    {
        $profile = $this->createProfessionalProfile();

        $active = $this->createService($profile, [
            'name' => 'Active public service',
            'is_active' => true,
        ]);
        $inactive = $this->createService($profile, [
            'name' => 'Inactive service',
            'is_active' => false,
        ]);
        $future = $this->createService($profile, [
            'name' => 'Future service',
            'starts_at' => '2026-06-10',
        ]);

        $response = $this->getJson("/api/v1/public/professionals/{$profile->id}");

        $response
            ->assertOk()
            ->assertJsonPath('professional.services.0.id', $active->id);

        $serviceNames = collect($response->json('professional.services'))->pluck('name')->all();

        $this->assertContains($active->name, $serviceNames);
        $this->assertNotContains($inactive->name, $serviceNames);
        $this->assertNotContains($future->name, $serviceNames);
    }

    public function test_show_does_not_expose_user_email(): void
    {
        $profile = $this->createProfessionalProfile(userOverrides: [
            'email' => 'private@example.test',
        ]);

        $response = $this->getJson("/api/v1/public/professionals/{$profile->id}");

        $response
            ->assertOk()
            ->assertJsonMissingPath('professional.user.email');
    }

    public function test_missing_professional_returns_404_json(): void
    {
        $missingId = (string) Str::uuid();

        $response = $this->getJson("/api/v1/public/professionals/{$missingId}");

        $response
            ->assertNotFound()
            ->assertJsonPath('error.type', 'NotFound');
    }

    public function test_professional_with_deleted_user_returns_404_json(): void
    {
        $profile = $this->createProfessionalProfile();
        $profile->user->delete();

        $response = $this->getJson("/api/v1/public/professionals/{$profile->id}");

        $response
            ->assertNotFound()
            ->assertJsonPath('error.type', 'NotFound');
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
}
