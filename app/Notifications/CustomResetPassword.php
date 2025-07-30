<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class CustomResetPassword extends ResetPassword
{
    public function toMail($notifiable)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:4200');
        // Agrega #/ antes de reset-password para que Angular reconozca la ruta con hash
        $resetUrl = "{$frontendUrl}/#/reset-password/{$this->token}";

        return (new MailMessage)
            ->subject('Restablecer contraseña')
            ->greeting('Hola!')
            ->line('Recibiste este correo porque solicitaste restablecer tu contraseña.')
            ->action('Restablecer contraseña', $resetUrl)
            ->line('Si no hiciste esta solicitud, puedes ignorar este mensaje.');
    }
}
