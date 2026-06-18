<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Booking\Booking;
use App\Models\Package\PackageProduct;
use App\Models\Payment\Payment;
use App\Models\Review\Review;
use App\Models\Service\Service;
use App\Models\User\User;
use Illuminate\Http\JsonResponse;

class AdminMetricsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'users_total' => User::query()->count(),
                'clients_total' => User::query()->where('role', UserRole::Client->value)->count(),
                'professionals_total' => User::query()->where('role', UserRole::Professional->value)->count(),
                'admins_total' => User::query()->where('role', UserRole::Admin->value)->count(),
                'bookings_total' => Booking::query()->count(),
                'bookings_today' => Booking::query()->whereDate('starts_at', today())->count(),
                'services_total' => Service::query()->count(),
                'reviews_total' => Review::query()->count(),
                'packages_total' => PackageProduct::query()->count(),
                'payments_total' => Payment::query()->count(),
            ],
        ]);
    }
}
