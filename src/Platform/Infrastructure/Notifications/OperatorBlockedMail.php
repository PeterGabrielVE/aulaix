<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Notifications;

use AulaX\Platform\Domain\CodeFailure;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the locked-out operator and every active SuperAdmin after 10
 * consecutive failed code attempts (SPEC-017 RSP-R03.6). Includes the dates
 * and IPs of the attempts, never any code or secret.
 */
final class OperatorBlockedMail extends Notification
{
    /**
     * @param  list<CodeFailure>  $failures
     */
    public function __construct(
        public readonly string $operatorName,
        public readonly string $operatorEmail,
        public readonly array $failures,
        public readonly bool $forSelf,
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
        $ips = array_values(array_unique(array_map(fn (CodeFailure $failure) => $failure->ip !== '' ? $failure->ip : 'desconocida', $this->failures)));
        $lastAt = $this->failures !== [] ? $this->failures[array_key_last($this->failures)]->occurredAt->format('Y-m-d H:i:s T') : '';

        $mail = (new MailMessage)
            ->subject($this->forSelf
                ? 'Tu acceso al panel de AulaX fue bloqueado temporalmente'
                : "Acceso de operador bloqueado en AulaX: {$this->operatorName}")
            ->line($this->forSelf
                ? 'Tu acceso al panel de plataforma se bloqueó durante 15 minutos tras 10 códigos fallidos seguidos y se cerraron tus sesiones abiertas.'
                : "El operador {$this->operatorName} ({$this->operatorEmail}) fue bloqueado durante 15 minutos tras 10 códigos fallidos seguidos y se cerraron sus sesiones abiertas.")
            ->line("Fecha del último intento: {$lastAt}")
            ->line('Direcciones IP de los intentos: '.implode(', ', $ips));

        return $mail->line('Si no reconoces estos intentos, cambia la contraseña y coordina el restablecimiento del segundo factor con un Superadministrador.');
    }
}
