<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
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
        $url = route('invitation.show', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        $days = intdiv(config('auth.passwords.invitations.expire'), 60 * 24);

        return (new MailMessage)
            ->subject("Invitación a {$institution->name} en AulaX")
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("{$institution->name} te ha creado una cuenta en AulaX.")
            ->line('Para activarla, elige tu contraseña:')
            ->action('Activar mi cuenta', $url)
            ->line("Este enlace vence en {$days} días.")
            ->line('Si no esperabas esta invitación, puedes ignorar este correo.');
    }
}
