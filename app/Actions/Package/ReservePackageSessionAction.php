<?php

namespace App\Actions\Package;

use App\Enums\Package\ClientPackageStatus;
use App\Enums\Package\PackageSessionStatus;
use App\Events\Package\PackageSessionReserved;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageSession;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReservePackageSessionAction
{
    public function __invoke(ClientPackage $clientPackage, Booking $booking): PackageSession
    {
        $clientPackage = ClientPackage::query()
            ->whereKey($clientPackage->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($clientPackage->client_id !== $booking->client_id) {
            throw new ApiException(
                error: 'Forbidden',
                message: 'No puedes usar este paquete.',
                status: Response::HTTP_FORBIDDEN
            );
        }

        if ($clientPackage->professional_id !== $booking->professional_id) {
            throw new ApiException(
                error: 'ClientPackageProfessionalMismatch',
                message: 'Este paquete no corresponde a este profesional.',
                status: Response::HTTP_CONFLICT
            );
        }

        if ($clientPackage->service_id !== null && $clientPackage->service_id !== $booking->service_id) {
            throw new ApiException(
                error: 'ClientPackageServiceMismatch',
                message: 'Este paquete no aplica para este servicio.',
                status: Response::HTTP_CONFLICT
            );
        }

        if ($clientPackage->expires_at !== null && $clientPackage->expires_at->isPast()) {
            $clientPackage->update([
                'status' => ClientPackageStatus::Expired,
            ]);

            throw new ApiException(
                error: 'ClientPackageExpired',
                message: 'Este paquete vencio.',
                status: Response::HTTP_CONFLICT
            );
        }

        if ($clientPackage->status === ClientPackageStatus::Depleted || ! $clientPackage->hasRemainingSessions()) {
            throw new ApiException(
                error: 'ClientPackageDepleted',
                message: 'Este paquete no tiene sesiones disponibles.',
                status: Response::HTTP_CONFLICT
            );
        }

        if ($clientPackage->status !== ClientPackageStatus::Active) {
            throw new ApiException(
                error: 'ClientPackageNotActive',
                message: 'Este paquete no esta activo.',
                status: Response::HTTP_CONFLICT
            );
        }

        if ($booking->packageSession()->exists() || $booking->client_package_id !== null) {
            throw new ApiException(
                error: 'BookingAlreadyUsesPackage',
                message: 'Esta reserva ya tiene una sesion de paquete asociada.',
                status: Response::HTTP_CONFLICT
            );
        }

        $session = PackageSession::create([
            'client_package_id' => $clientPackage->id,
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'professional_id' => $booking->professional_id,
            'status' => PackageSessionStatus::Reserved,
        ]);

        $clientPackage->update([
            'used_sessions' => $clientPackage->used_sessions + 1,
        ]);

        $clientPackage->refresh();

        if ($clientPackage->remainingSessions() <= 0) {
            $clientPackage->update([
                'status' => ClientPackageStatus::Depleted,
                'depleted_at' => now(),
            ]);
        }

        $booking->update([
            'client_package_id' => $clientPackage->id,
        ]);

        DB::afterCommit(function () use ($session): void {
            event(new PackageSessionReserved($session->id));
        });

        return $session;
    }
}
