<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The invitation email. Its link goes to the central domain, where the
 * account is created or authenticated (SPEC-004).
 */
final class InvitationMail extends Notification
{
    public function __construct(
        public readonly string $name,
        public readonly string $institutionName,
        #[\SensitiveParameter] public readonly string $url,
        public readonly int $validForDays,
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
            ->subject("Invitación a {$this->institutionName} en AulaX")
            ->greeting("¡Hola, {$this->name}!")
            ->line("{$this->institutionName} te invita a unirte en AulaX.")
            ->line('Para aceptar, elige tu contraseña:')
            ->action('Aceptar la invitación', $this->url)
            ->line("Este enlace vence en {$this->validForDays} días.")
            ->line('Si no esperabas esta invitación, puedes ignorar este correo.');
    }
}
