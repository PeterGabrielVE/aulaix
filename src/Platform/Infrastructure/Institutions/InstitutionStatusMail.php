<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Institutions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The notice to an Administrador that the platform deactivated or
 * reactivated their institution, and why (SPEC-014 PLT-R05.3).
 */
final class InstitutionStatusMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $institution,
        public readonly bool $active,
        public readonly string $reason,
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
        $message = (new MailMessage)
            ->subject($this->active ? "{$this->institution} vuelve a estar activo en AulaX" : "{$this->institution} se desactivó en AulaX")
            ->greeting("¡Hola, {$this->name}!")
            ->line($this->active
                ? "La plataforma reactivó {$this->institution}. Sus miembros ya pueden entrar de nuevo."
                : "La plataforma desactivó {$this->institution}. Mientras siga así, nadie podrá entrar; sus datos se conservan.");

        if ($this->reason !== '') {
            $message->line("Motivo: {$this->reason}");
        }

        return $message->line('Si tienes dudas, responde a este correo.');
    }
}
