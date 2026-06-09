<?php

namespace App\Actions\Booking;

use App\Models\Booking\Booking;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;

class ListMyBookingsAction
{
    public function __invoke(User $user): Collection
    {
        return Booking::query()
            ->with(['service.professional.user', 'professional.user', 'payment', 'clientPackage', 'packageSession'])
            ->withExists('videoSession')
            ->where('client_id', $user->id)
            ->latest('starts_at')
            ->get();
    }
}
