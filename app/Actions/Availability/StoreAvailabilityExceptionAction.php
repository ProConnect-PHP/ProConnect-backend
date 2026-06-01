<?php

namespace App\Actions\Availability;

use App\Http\Requests\Availability\StoreAvailabilityExceptionRequest;
use App\Models\Availability\AvailabilityException;
use App\Models\Service\Service;

class StoreAvailabilityExceptionAction
{
    public function __invoke(
        Service $service,
        StoreAvailabilityExceptionRequest $request
    ): AvailabilityException {
        return AvailabilityException::create([
            ...$request->validated(),
            'service_id' => $service->id,
        ]);
    }
}
