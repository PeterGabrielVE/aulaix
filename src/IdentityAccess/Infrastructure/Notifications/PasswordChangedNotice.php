<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner their password changed, so a change they did not make
 * does not go unnoticed (IDG-R06.1).
 */
final class PasswordChangedNotice extends Notification
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
            ->subject('Tu contraseña de AulaX cambió')
            ->greeting("¡Hola, {$this->name}!")
            ->line('La contraseña de tu cuenta de AulaX acaba de cambiar, y cerramos todas tus sesiones abiertas.')
            ->line('Si no fuiste tú, restablécela de inmediato desde «¿Olvidaste tu contraseña?» y avisa a la administración de tu plantel.');
    }
}
