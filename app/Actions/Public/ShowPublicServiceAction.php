<?php

namespace App\Actions\Public;

use App\Exceptions\ApiException;
use App\Models\Service\Service;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ShowPublicServiceAction
{
    public function __invoke(Service $service): Service
    {
        if (! $this->isPubliclyVisible($service)) {
            throw new ApiException(
                error: 'NotFound',
                message: 'Servicio no encontrado.',
                status: Response::HTTP_NOT_FOUND
            );
        }

        return $service->load([
            'professional.user',
            'company',
            'availabilityRules' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time'),
        ]);
    }

    private function isPubliclyVisible(Service $service): bool
    {
        $today = Carbon::today()->toDateString();

        return $service->is_active
            && $service->professional()->whereHas('user')->exists()
            && ($service->starts_at === null || $service->starts_at->toDateString() <= $today)
            && ($service->ends_at === null || $service->ends_at->toDateString() >= $today);
    }
}
