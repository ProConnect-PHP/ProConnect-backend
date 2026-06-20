<?php

namespace App\Actions\Payment;

use App\Models\Payment\Payment;
use App\Models\User\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListMyPaymentsAction
{
    public function __invoke(
        User $client,
        int $perPage = 10,
        int $page = 1,
    ): LengthAwarePaginator {
        return Payment::query()
            ->with(['booking', 'packageProduct', 'clientPackage'])
            ->where('client_id', $client->id)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
