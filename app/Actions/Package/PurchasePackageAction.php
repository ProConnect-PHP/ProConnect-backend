<?php

namespace App\Actions\Package;

use App\Enums\Package\ClientPackageStatus;
use App\Events\Package\PackagePurchased;
use App\Exceptions\ApiException;
use App\Models\Package\ClientPackage;
use App\Models\Package\PackageProduct;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PurchasePackageAction
{
    public function __invoke(PackageProduct $packageProduct, User $client, array $data = []): ClientPackage
    {
        return DB::transaction(function () use ($packageProduct, $client, $data) {
            $packageProduct = PackageProduct::query()
                ->whereKey($packageProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $packageProduct->is_active) {
                throw new ApiException(
                    error: 'PackageNotAvailable',
                    message: 'Este paquete no esta disponible.',
                    status: Response::HTTP_CONFLICT
                );
            }

            if ($client->professionalProfile?->id === $packageProduct->professional_id) {
                    throw new ApiException(
                    error: 'CannotPurchaseOwnPackage',
                    message: 'No puedes comprar tu propio paquete.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $alreadyPurchased = ClientPackage::query()
                ->where('package_product_id', $packageProduct->id)
                ->where('client_id', $client->id)
                ->exists();

            if ($alreadyPurchased) {
                throw new ApiException(
                error: 'PackageAlreadyPurchased',
                message: 'Ya has adquirido este paquete. Solo se permite un paquete por persona.',
                status: Response::HTTP_CONFLICT
                );
            }
            
            $purchasedAt = now();

            $clientPackage = ClientPackage::create([
                'package_product_id' => $packageProduct->id,
                'client_id' => $client->id,
                'professional_id' => $packageProduct->professional_id,
                'service_id' => $packageProduct->service_id,
                'status' => ClientPackageStatus::Active,
                'total_sessions' => $packageProduct->sessions_count,
                'used_sessions' => 0,
                'price_snapshot' => $packageProduct->price,
                'currency' => $packageProduct->currency,
                'purchased_at' => $purchasedAt,
                'expires_at' => $packageProduct->validity_days
                    ? $purchasedAt->copy()->addDays($packageProduct->validity_days)
                    : null,
                'metadata' => [
                    ...($data['metadata'] ?? []),
                    'purchase_mode' => 'simulated',
                ],
            ])->load(['packageProduct.service', 'service', 'client', 'professional.user']);

            DB::afterCommit(function () use ($clientPackage): void {
                event(new PackagePurchased($clientPackage->id));
            });

            return $clientPackage;
        });
    }
}
