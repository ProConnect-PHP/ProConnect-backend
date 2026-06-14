<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('proconnect.frontend_url', config('app.url')), '/');

        $email = $notifiable->getEmailForPasswordReset();

        $resetUrl = $frontendUrl . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);

        $passwordBroker = config('auth.defaults.passwords', 'users');

        $expirationMinutes = config("auth.passwords.{$passwordBroker}.expire", 60);

        return (new MailMessage)
            ->subject('Restablecer contraseña | ' . config('app.name', 'ProConnect'))
            ->markdown('emails.auth.reset-password', [
                'name' => $notifiable->name,
                'resetUrl' => $resetUrl,
                'expirationMinutes' => $expirationMinutes,
            ]);
    }
}
