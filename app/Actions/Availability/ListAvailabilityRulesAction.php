<?php

namespace App\Actions\Availability;

use App\Models\Service\Service;
use Illuminate\Database\Eloquent\Collection;

class ListAvailabilityRulesAction
{
    public function __invoke(Service $service): Collection
    {
        return $service
            ->availabilityRules()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }
}
