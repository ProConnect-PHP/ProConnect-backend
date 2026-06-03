<?php

namespace Database\Seeders\Demo;

use App\Models\Booking\Booking;
use App\Models\Review\Review;
use App\Models\Review\ReviewReply;
use App\Enums\Booking\BookingStatus;
use Illuminate\Database\Seeder;

/**
 * Seeds demo reviews and professional replies.
 *
 * Creates reviews only for completed bookings.
 * Some reviews have comments, some don't.
 * Creates replies for ~60% of reviews from professional accounts.
 */
class DemoReviewSeeder extends Seeder
{
    private array $commentTexts = [
        'Excelente atención, me sentí muy cómodo durante toda la sesión.',
        'Muy profesional y claro en las recomendaciones.',
        'La sesión fue puntual y me ayudó a ordenar mis próximos pasos.',
        'Buena experiencia, volvería a reservar.',
        'El servicio cumplió mis expectativas.',
        'Muy recomendado, volveré pronto.',
        'Profesional, atento y resolutivo.',
        'Excelente servicio, superó expectativas.',
    ];

    private array $replyTexts = [
        'Muchas gracias por tu comentario. Me alegra que la sesión haya sido útil.',
        'Gracias por confiar en mi trabajo. Seguimos en contacto para próximos pasos.',
        'Valoro mucho tu devolución, fue un gusto acompañarte.',
        'Feliz de haber podido ayudarte. ¡Nos vemos pronto!',
        'Tu feedback es muy importante. ¡Gracias por tu confianza!',
        'Fue un placer trabajar contigo. Adelante con los objetivos.',
    ];

    /**
     * Run the seeder.
     */
    public function run(): void
    {
        $completedBookings = Booking::where('status', BookingStatus::Completed)
            ->with(['service', 'professional', 'client'])
            ->get()
            ->filter(function ($booking) {
                // Only create reviews for bookings without existing reviews
                return !$booking->review;
            });

        if ($completedBookings->isEmpty()) {
            $this->command?->warn('⚠ No completed bookings found for reviews');
            return;
        }

        $reviewCount = 0;
        $replyCount = 0;

        foreach ($completedBookings as $booking) {
            // Create review with varying ratings and some without comments
            $rating = $this->getRandomRating();
            $hasComment = rand(1, 10) > 3; // 70% with comments
            $comment = $hasComment ? $this->getRandomComment() : null;

            $review = Review::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'service_id' => $booking->service_id,
                    'professional_id' => $booking->professional_id,
                    'client_id' => $booking->client_id,
                    'rating' => $rating,
                    'comment' => $comment,
                ]
            );

            $reviewCount++;

            // Create replies for 60% of reviews
            if (rand(1, 100) <= 60 && !$review->reply) {
                ReviewReply::updateOrCreate(
                    ['review_id' => $review->id],
                    [
                        'professional_id' => $booking->professional_id,
                        'body' => $this->getRandomReply(),
                    ]
                );
                $replyCount++;
            }
        }

        // Recalculate professional ratings and review counts
        $this->recalculateProfessionalStats();

        $this->command?->info("✓ Demo reviews created/updated ({$reviewCount} reviews, {$replyCount} replies)");
    }

    /**
     * Get a random rating with weighted distribution.
     * More 5-star reviews, fewer 1-star.
     */
    private function getRandomRating(): int
    {
        $rand = rand(1, 100);

        // Weighted distribution
        if ($rand <= 35) return 5;
        if ($rand <= 60) return 4;
        if ($rand <= 80) return 3;
        if ($rand <= 92) return 2;
        return 1;
    }

    /**
     * Get a random comment from the predefined list.
     */
    private function getRandomComment(): string
    {
        return $this->commentTexts[array_rand($this->commentTexts)];
    }

    /**
     * Get a random reply from the predefined list.
     */
    private function getRandomReply(): string
    {
        return $this->replyTexts[array_rand($this->replyTexts)];
    }

    /**
     * Recalculate professional average ratings and review counts.
     */
    private function recalculateProfessionalStats(): void
    {
        $professionals = \App\Models\User\ProfessionalProfile::all();

        foreach ($professionals as $professional) {
            $reviews = Review::where('professional_id', $professional->id)
                ->whereNotNull('rating')
                ->get();

            if ($reviews->isNotEmpty()) {
                $professional->update([
                    'avg_rating' => round($reviews->avg('rating'), 2),
                    'reviews_count' => $reviews->count(),
                ]);
            }
        }
    }
}
