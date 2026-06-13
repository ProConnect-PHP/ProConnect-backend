<?php

namespace App\Listeners\Payment;

use App\Actions\Notification\QueueBookingEmailNotificationAction;
use App\Events\Payment\PaymentSucceeded;
use App\Mail\Payment\PaymentSucceededForClientMail;
use App\Mail\Payment\PaymentSucceededForProfessionalMail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPaymentSucceededNotifications implements ShouldQueue
{
    public string $queue = 'emails';

    public bool $afterCommit = true;

    public function handle(PaymentSucceeded $event): void
    {
        $payment = $event->payment->loadMissing([
            'booking.service',
            'client',
            'professional.user',
        ]);

        if (! $payment->booking) {
            return;
        }

        if ($payment->client) {
            app(QueueBookingEmailNotificationAction::class)(
                booking: $payment->booking,
                recipient: $payment->client,
                type: 'payment_succeeded_client',
                mail: new PaymentSucceededForClientMail($payment),
                payload: ['payment_id' => $payment->id],
            );
        }

        $professionalUser = $payment->professional?->user;

        if (! $professionalUser) {
            return;
        }

        app(QueueBookingEmailNotificationAction::class)(
            booking: $payment->booking,
            recipient: $professionalUser,
            type: 'payment_succeeded_professional',
            mail: new PaymentSucceededForProfessionalMail($payment),
            payload: ['payment_id' => $payment->id],
        );
    }
}
