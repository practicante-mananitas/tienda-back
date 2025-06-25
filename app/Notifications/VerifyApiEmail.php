<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyApiEmail extends VerifyEmailBase
{
    protected function verificationUrl($notifiable)
    {
        // Genera la URL firmada que apunta al backend Laravel (no al frontend Angular)
        return URL::temporarySignedRoute(
            'verification.verify',  // Ruta backend (web.php)
            Carbon::now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification())
            ]
        );
    }


    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Verifica tu correo electrónico')
            ->line('Haz clic en el siguiente botón para verificar tu correo electrónico.')
            ->action('Verificar Correo', $this->verificationUrl($notifiable))
            ->line('Si no creaste esta cuenta, no hagas nada.');
    }
}
