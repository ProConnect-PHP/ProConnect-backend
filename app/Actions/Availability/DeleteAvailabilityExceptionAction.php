<?php

namespace App\Actions\Availability;

use App\Models\Availability\AvailabilityException;

class DeleteAvailabilityExceptionAction
{
    public function __invoke(
        AvailabilityException $availabilityException
    ): void {
        $availabilityException->delete();
    }
}
