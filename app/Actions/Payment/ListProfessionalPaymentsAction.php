<?php

namespace App\Actions\Payment;

use App\Models\Payment\Payment;
use App\Models\User\ProfessionalProfile;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProfessionalPaymentsAction
{
    public function __invoke(ProfessionalProfile $professionalProfile, int $perPage = 10): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['booking'])
            ->where('professional_id', $professionalProfile->id)
            ->latest('paid_at')
            ->paginate(min($perPage, 50));
    }
}
