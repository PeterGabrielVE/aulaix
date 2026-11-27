<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent instead of a reset link when the email still has one account per
 * institution from before SPEC-004: there is no single password to reset
 * until they are consolidated.
 */
final class ConsolidationGuidance extends Notification
{
    public function __construct(
        public readonly string $name,
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
            ->subject('Tus cuentas de AulaX')
            ->greeting("¡Hola, {$this->name}!")
            ->line('Recibimos una solicitud para restablecer tu contraseña, pero tu correo tiene una cuenta en cada uno de tus planteles.')
            ->line('Inicia sesión con la contraseña de cualquiera de ellos y unifica tus cuentas en una sola, con una única contraseña.')
            ->action('Iniciar sesión', $this->loginUrl)
            ->line('Si no recuerdas ninguna, pide ayuda a la administración de tu plantel.');
    }
}
