<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ConsolidationCode extends Notification
{
    public function __construct(
        public readonly string $name,
        #[\SensitiveParameter] public readonly string $code,
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
            ->subject('Tu código para unificar tus cuentas de AulaX')
            ->greeting("¡Hola, {$this->name}!")
            ->line('Usa este código para unificar tus cuentas de AulaX en una sola:')
            ->line($this->code)
            ->line('Vence en 15 minutos y solo puede usarse una vez.')
            ->line('Si no lo pediste, ignora este correo y cambia tu contraseña.');
    }
}
