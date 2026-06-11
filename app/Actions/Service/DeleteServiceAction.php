<?php

namespace App\Actions\Service;

use App\Models\Service\Service;
use Illuminate\Support\Facades\Auth;

class DeleteServiceAction
{
    public function __invoke(Service $service): void
    {
        if($service->bookings()->exists()) {
            throw new \Exception('No se puede eliminar un servicio que tiene reservas asociadas.');
        }

        $service->delete();
    }
}
