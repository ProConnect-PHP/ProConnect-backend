<?php

namespace App\Actions\Booking;

use App\Models\Booking\Booking;
use App\Models\User\ProfessionalProfile;
use Illuminate\Database\Eloquent\Collection;

class ListProfessionalBookingsAction
{
    public function __invoke(ProfessionalProfile $professionalProfile): Collection
    {
        return Booking::query()
            ->with(['service', 'client', 'payment', 'clientPackage', 'packageSession'])
            ->withExists('videoSession')
            ->where('professional_id', $professionalProfile->id)
            ->latest('starts_at')
            ->get();
    }
}
