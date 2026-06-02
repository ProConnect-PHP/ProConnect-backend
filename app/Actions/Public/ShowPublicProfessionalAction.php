<?php

namespace App\Actions\Public;

use App\Exceptions\ApiException;
use App\Models\User\ProfessionalProfile;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ShowPublicProfessionalAction
{
    public function __invoke(ProfessionalProfile $professionalProfile): ProfessionalProfile
    {
        if (! $professionalProfile->user()->exists()) {
            throw new ApiException(
                error: 'NotFound',
                message: 'Profesional no encontrado.',
                status: Response::HTTP_NOT_FOUND
            );
        }

        $today = Carbon::today()->toDateString();

        return $professionalProfile->load([
            'user',
            'services' => function ($query) use ($today) {
                $query
                    ->where('is_active', true)
                    ->where(function ($query) use ($today) {
                        $query
                            ->whereNull('starts_at')
                            ->orWhereDate('starts_at', '<=', $today);
                    })
                    ->where(function ($query) use ($today) {
                        $query
                            ->whereNull('ends_at')
                            ->orWhereDate('ends_at', '>=', $today);
                    })
                    ->orderByDesc('created_at');
            },
            'services.company',
        ]);
    }
}
