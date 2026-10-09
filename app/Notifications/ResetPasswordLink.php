<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Forgot your password?" email. Names the institution because the same
 * address can hold separate accounts in several of them, and each link
 * only works on the subdomain it was requested from.
 */
class ResetPasswordLink extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $institution = $notifiable->institution;

        // Sent from a tenant request, so route() already resolves to this
        // institution's subdomain (ResolveTenant binds the URL default).
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject("Restablece tu contraseña de {$institution->name} en AulaX")
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Recibimos una solicitud para restablecer la contraseña de tu cuenta de {$institution->name} en AulaX.")
            ->action('Restablecer contraseña', $url)
            ->line("Este enlace vence en {$minutes} minutos y solo puede usarse una vez.")
            ->line('Si no lo solicitaste, puedes ignorar este correo: tu contraseña no cambiará.');
    }
}
