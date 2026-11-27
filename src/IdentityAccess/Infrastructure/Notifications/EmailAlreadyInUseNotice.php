<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To an address that already has an account, when someone asked to move
 * their own account to it (IDG-R05.4). Says nothing about who asked.
 */
final class EmailAlreadyInUseNotice extends Notification
{
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
            ->subject('Intento de usar tu correo en AulaX')
            ->line('Alguien intentó usar esta dirección para otra cuenta de AulaX, pero ya es la de tu cuenta, así que no se cambió nada.')
            ->line('Si fuiste tú, no hace falta hacer nada. Si no, puedes ignorar este correo.');
    }
}
