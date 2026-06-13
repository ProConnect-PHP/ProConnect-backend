<?php

namespace App\Actions\Payment;

use App\Models\Payment\Payment;
use App\Models\User\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListClientPaymentsAction
{
    public function __invoke(User $client, int $perPage = 10): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['booking', 'packageProduct', 'clientPackage'])
            ->where('client_id', $client->id)
            ->latest('paid_at')
            ->paginate(min($perPage, 50));
    }
}
