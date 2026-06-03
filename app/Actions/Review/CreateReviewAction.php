<?php

namespace App\Actions\Review;

use App\Enums\Booking\BookingStatus;
use App\Exceptions\ApiException;
use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\User\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CreateReviewAction
{
    public function __construct(
        private readonly RecalculateProfessionalRatingAction $recalculateProfessionalRating
    ) {
    }

    public function __invoke(Booking $booking, User $client, array $data): Review
    {
        return DB::transaction(function () use ($booking, $client, $data) {
            $booking = Booking::query()
                ->with(['professional', 'review'])
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->client_id !== $client->id) {
                throw new ApiException(
                    error: 'Forbidden',
                    message: 'No puedes reseñar esta reserva.',
                    status: Response::HTTP_FORBIDDEN
                );
            }

            if ($booking->status !== BookingStatus::Completed) {
                throw new ApiException(
                    error: 'BookingNotCompleted',
                    message: 'Solo puedes reseñar reservas finalizadas.',
                    status: Response::HTTP_CONFLICT
                );
            }

            if ($booking->review()->exists()) {
                throw new ApiException(
                    error: 'BookingAlreadyReviewed',
                    message: 'Esta reserva ya tiene una reseña.',
                    status: Response::HTTP_CONFLICT
                );
            }

            $review = Review::create([
                'booking_id' => $booking->id,
                'service_id' => $booking->service_id,
                'professional_id' => $booking->professional_id,
                'client_id' => $client->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            ($this->recalculateProfessionalRating)($booking->professional);

            return $review->load(['client', 'reply.professional.user']);
        });
    }
}
