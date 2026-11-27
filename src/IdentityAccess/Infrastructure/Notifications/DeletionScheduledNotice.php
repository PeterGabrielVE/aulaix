<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * IDG-R08.5: when the deletion was asked, when the account closes, and how
 * to cancel it.
 */
final class DeletionScheduledNotice extends Notification
{
    public function __construct(
        public readonly string $name,
        public readonly string $requestedOn,
        public readonly string $closesOn,
        public readonly string $loginUrl,
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
            ->subject('Tu cuenta de AulaX se eliminará')
            ->greeting("¡Hola, {$this->name}!")
            ->line("El {$this->requestedOn} pediste eliminar tu cuenta de AulaX. Se cerrará el {$this->closesOn}.")
            ->line('Hasta entonces no puedes entrar a tus planteles, pero puedes arrepentirte: inicia sesión y cancela la eliminación.')
            ->action('Cancelar la eliminación', $this->loginUrl)
            ->line('Los registros académicos y administrativos de tus planteles se conservan.');
    }
}
