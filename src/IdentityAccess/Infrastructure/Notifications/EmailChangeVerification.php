<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the new address: the change only happens when this link is used
 * (IDG-R05.2).
 */
final class EmailChangeVerification extends Notification
{
    public function __construct(
        public readonly string $name,
        #[\SensitiveParameter] public readonly string $url,
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
            ->subject('Confirma tu nuevo correo en AulaX')
            ->greeting("¡Hola, {$this->name}!")
            ->line('Pediste usar esta dirección para tu cuenta de AulaX. Confírmala con este enlace:')
            ->action('Confirmar mi nuevo correo', $this->url)
            ->line('El enlace vence en 24 horas. Hasta entonces, tu correo actual sigue siendo el de tu cuenta.')
            ->line('Si no lo pediste, ignora este correo.');
    }
}
