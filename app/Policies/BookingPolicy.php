<?php

namespace App\Policies;

use App\Models\Booking\Booking;
use App\Models\User\User;

class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $this->isClient($user, $booking)
            || $this->isProfessionalOwner($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $this->isClient($user, $booking)
            || $this->isProfessionalOwner($user, $booking);
    }

    public function confirm(User $user, Booking $booking): bool
    {
        return $this->isProfessionalOwner($user, $booking);
    }

    public function reschedule(User $user, Booking $booking): bool
    {
        return $this->isClient($user, $booking)
            || $this->isProfessionalOwner($user, $booking);
    }

    public function viewAvailableActions(User $user, Booking $booking): bool
    {
        return $this->isClient($user, $booking);
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $user->canActAsClient() && $this->isClient($user, $booking);
    }

    public function joinVideoSession(User $user, Booking $booking): bool
    {
        return $this->view($user, $booking);
    }

    private function isClient(User $user, Booking $booking): bool
    {
        return $booking->client_id === $user->id;
    }

    private function isProfessionalOwner(User $user, Booking $booking): bool
    {
        return $user->professionalProfile?->id === $booking->professional_id;
    }
}
