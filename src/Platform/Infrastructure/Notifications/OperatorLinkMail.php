<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Notifications;

use AulaX\Platform\Domain\InvitationPurpose;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The link to join the panel or enrol a TOTP again (PLT-R03.1, R03.7). The
 * token travels only here (V4).
 */
final class OperatorLinkMail extends Notification
{
    public function __construct(
        public readonly string $name,
        #[\SensitiveParameter] public readonly string $token,
        public readonly InvitationPurpose $purpose,
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
        $url = route('platform.invitation.show', ['token' => $this->token]);
        $joining = $this->purpose === InvitationPurpose::Onboarding;

        return (new MailMessage)
            ->subject($joining ? 'Tu acceso al panel de AulaX' : 'Configura de nuevo tu segundo factor en AulaX')
            ->greeting("¡Hola, {$this->name}!")
            ->line($joining
                ? 'Te invitaron como operador de la plataforma AulaX. Elige tu contraseña y configura tu app de autenticación:'
                : 'Se restableció tu segundo factor. Configura de nuevo tu app de autenticación:')
            ->action($joining ? 'Activar mi acceso' : 'Configurar el segundo factor', $url)
            ->line('Este enlace vence en 48 horas y solo se puede usar una vez.')
            ->line('Si no esperabas este correo, avisa a un Superadministrador.');
    }
}
