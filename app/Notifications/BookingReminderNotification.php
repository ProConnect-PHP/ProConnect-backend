<?php

namespace App\Notifications;

use App\Models\Booking\Booking;
use App\Models\Booking\ProfessionalBookingReminderRule;
use App\Notifications\Channels\BookingReminderDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
        public readonly ProfessionalBookingReminderRule $rule,
        public readonly string $recipientType
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->rule->send_email) {
            $channels[] = 'mail';
        }

        if ($this->rule->send_database_notification) {
            $channels[] = BookingReminderDatabaseChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $counterpartName = $this->recipientType === 'client'
            ? $this->booking->professional?->user?->name
            : $this->booking->client?->name;

        $message = (new MailMessage)
            ->subject('Recordatorio de sesión')
            ->greeting('Recordatorio de sesión')
            ->line(
                'Tienes una sesión programada con '.$counterpartName
                .' el '.$this->booking->starts_at->format('d/m/Y')
                .' a las '.$this->booking->starts_at->format('H:i').'.'
            )
            ->line('Servicio: '.$this->booking->service?->name)
            ->line('Modalidad: '.$this->booking->modality);

        $clientPackage = $this->booking->clientPackage;

        if ($clientPackage?->packageProduct) {
            $message
                ->line('Paquete: '.$clientPackage->packageProduct->name)
                ->line('Sesiones restantes: '.$clientPackage->remainingSessions());
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        $counterpartName = $this->recipientType === 'client'
            ? $this->booking->professional?->user?->name
            : $this->booking->client?->name;

        return [
            'title' => 'Recordatorio de sesión',
            'booking_id' => $this->booking->id,
            'recipient_type' => $this->recipientType,
            'counterpart_name' => $counterpartName,
            'service_name' => $this->booking->service?->name,
            'starts_at' => $this->booking->starts_at?->toIso8601String(),
            'modality' => $this->booking->modality,
            'package_name' => $this->booking->clientPackage?->packageProduct?->name,
            'remaining_sessions' => $this->booking->clientPackage?->remainingSessions(),
        ];
    }

    public function notificationType(): string
    {
        return 'booking_reminder_'.$this->rule->id.'_'.$this->recipientType;
    }
}
