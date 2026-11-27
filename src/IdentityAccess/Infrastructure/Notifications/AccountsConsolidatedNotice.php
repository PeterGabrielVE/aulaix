<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountsConsolidatedNotice extends Notification
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
            ->subject('Tus cuentas de AulaX están unificadas')
            ->greeting("¡Hola, {$this->name}!")
            ->line('Tus cuentas de AulaX ahora son una sola, con todos tus planteles, y cerramos las sesiones abiertas de las anteriores.')
            ->line('Si no fuiste tú, restablece tu contraseña de inmediato y avisa a soporte.');
    }
}
