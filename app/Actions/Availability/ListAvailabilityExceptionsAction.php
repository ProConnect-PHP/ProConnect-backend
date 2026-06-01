<?php

namespace App\Actions\Availability;

use App\Models\Service\Service;
use Illuminate\Database\Eloquent\Collection;

class ListAvailabilityExceptionsAction
{
    public function __invoke(Service $service): Collection
    {
        return $service
            ->availabilityExceptions()
            ->orderBy('exception_date')
            ->get();
    }
}
