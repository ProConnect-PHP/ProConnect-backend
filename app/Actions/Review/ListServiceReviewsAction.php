<?php

namespace App\Actions\Review;

use App\Models\Review\Review;
use App\Models\Service\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListServiceReviewsAction
{
    public function __invoke(Service $service, int $perPage = 10): LengthAwarePaginator
    {
        return Review::query()
            ->with(['client', 'reply.professional.user'])
            ->where('service_id', $service->id)
            ->latest()
            ->paginate(min($perPage, 50));
    }
}
