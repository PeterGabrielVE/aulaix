<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The "forgot your password?" email, linking to the central domain: the
 * password is the account's, for every institution (IDG-R06).
 */
final class ResetPasswordLink extends Notification
{
    public function __construct(
        public readonly string $name,
        #[\SensitiveParameter] public readonly string $token,
        #[\SensitiveParameter] public readonly string $url,
        public readonly int $validForMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Restablece tu contraseña de AulaX')
            ->greeting("¡Hola, {$this->name}!")
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta de AulaX.')
            ->action('Restablecer contraseña', $this->url)
            ->line("Este enlace vence en {$this->validForMinutes} minutos y solo puede usarse una vez.")
            ->line('Si no lo solicitaste, puedes ignorar este correo: tu contraseña no cambiará.');
    }
}
