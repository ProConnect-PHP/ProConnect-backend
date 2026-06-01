<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\UpdateAvailabilityExceptionRequest;
use App\Models\Availability\AvailabilityException;

class UpdateAvailabilityExceptionAction
{
    public function __invoke(
        AvailabilityException $availabilityException,
        UpdateAvailabilityExceptionRequest $request
    ): AvailabilityException {
        $availabilityException->update(
            $request->validated()
        );

        return $availabilityException->refresh();
    }
}
