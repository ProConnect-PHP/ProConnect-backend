<?php

namespace Tests\Feature\Package;

use App\Actions\Package\PurchasePackageAction;
use App\Events\Package\PackagePurchased;
use App\Listeners\Package\SendPackagePurchasedNotifications;
use App\Mail\Package\PackagePurchasedForClientMail;
use App\Mail\Package\PackagePurchasedForProfessionalMail;
use App\Models\Notification\NotificationLog;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\Service\Service;
use App\Models\User\ProfessionalProfile;
use App\Models\User\User;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PackageNotificationTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_purchase_package_dispatches_package_purchased_after_commit(): void
    {
        Event::fake([PackagePurchased::class]);
        [$client, $packageProduct] = $this->packageProductScenario();

        $clientPackage = app(PurchasePackageAction::class)($packageProduct, $client);

        Event::assertDispatched(
            PackagePurchased::class,
            fn (PackagePurchased $event): bool => $event->clientPackageId === $clientPackage->id
        );
    }

    public function test_package_purchased_listener_sends_emails_and_is_idempotent(): void
    {
        Mail::fake();
        [$clientPackage, $client, $professionalUser] = $this->purchasedPackageScenario();
        $listener = new SendPackagePurchasedNotifications();

        $listener->handle(new PackagePurchased($clientPackage->id));
        $listener->handle(new PackagePurchased($clientPackage->id));

        Mail::assertSent(PackagePurchasedForClientMail::class, 1);
        Mail::assertSent(PackagePurchasedForProfessionalMail::class, 1);
        Mail::assertSent(
            PackagePurchasedForClientMail::class,
            fn (PackagePurchasedForClientMail $mail): bool => $mail->hasTo($client->email)
        );
        Mail::assertSent(
            PackagePurchasedForProfessionalMail::class,
            fn (PackagePurchasedForProfessionalMail $mail): bool => $mail->hasTo($professionalUser->email)
        );

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $client->id,
            'client_package_id' => $clientPackage->id,
            'type' => 'package_purchased_client',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $professionalUser->id,
            'client_package_id' => $clientPackage->id,
            'type' => 'package_purchased_professional',
            'status' => 'sent',
        ]);
        $this->assertSame(
            2,
            NotificationLog::query()
                ->where('client_package_id', $clientPackage->id)
                ->whereIn('type', [
                    'package_purchased_client',
                    'package_purchased_professional',
                ])
                ->count()
        );
    }

    public function test_package_purchased_email_contains_package_details(): void
    {
        [$clientPackage] = $this->purchasedPackageScenario([
            'used_sessions' => 1,
        ]);

        $html = (new PackagePurchasedForClientMail($clientPackage->load([
            'client',
            'professional.user',
            'packageProduct.service',
            'service',
        ])))->render();

        $this->assertStringContainsString('Pack 4 sesiones online', $html);
        $this->assertStringContainsString('Sesiones incluidas', $html);
        $this->assertStringContainsString('Sesiones disponibles', $html);
        $this->assertStringContainsString('5.600', $html);
        $this->assertStringContainsString('31/07/2026', $html);
    }

    public function test_package_purchased_listener_is_queued_after_commit(): void
    {
        $listener = new SendPackagePurchasedNotifications();

        $this->assertInstanceOf(ShouldQueue::class, $listener);
        $this->assertSame('emails', $listener->queue);
        $this->assertTrue($listener->afterCommit);
    }

    private function packageProductScenario(): array
    {
        $client = User::factory()->create();
        $professionalUser = User::factory()->professional()->create();
        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $professionalUser->id,
        ]);
        $service = Service::factory()->create([
            'professional_id' => $professional->id,
            'name' => 'Terapia online individual',
            'price' => 1600,
            'duration_minutes' => 50,
        ]);
        $packageProduct = PackageProduct::factory()
            ->forService($service)
            ->active()
            ->create([
                'name' => 'Pack 4 sesiones online',
                'sessions_count' => 4,
                'price' => 5600,
                'validity_days' => 60,
            ]);

        return [$client, $packageProduct, $professionalUser];
    }

    private function purchasedPackageScenario(array $overrides = []): array
    {
        [$client, $packageProduct, $professionalUser] = $this->packageProductScenario();
        $clientPackage = ClientPackage::factory()
            ->forPackageProduct($packageProduct)
            ->active()
            ->create([
                'client_id' => $client->id,
                'purchased_at' => now(),
                'expires_at' => now()->addDays(60),
                ...$overrides,
            ]);

        return [$clientPackage, $client, $professionalUser];
    }
}
