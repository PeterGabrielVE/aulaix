<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To the previous address, once the new one is verified (IDG-R05.3), so a
 * change the owner did not make does not go unnoticed.
 */
final class EmailChangedNotice extends Notification
{
    public function __construct(public readonly string $name) {}

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
            ->subject('El correo de tu cuenta de AulaX cambió')
            ->greeting("¡Hola, {$this->name}!")
            ->line('Tu cuenta de AulaX ya no usa esta dirección: el nuevo correo fue confirmado.')
            ->line('Si no fuiste tú, contacta de inmediato a la administración de tu plantel.');
    }
}
