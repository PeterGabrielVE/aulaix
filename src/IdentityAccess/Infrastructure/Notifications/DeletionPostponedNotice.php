<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * IDG-R08.10: the account could not be closed yet. Sent to its owner and to
 * support; support's copy names the account by id only.
 */
final class DeletionPostponedNotice extends Notification
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly string $greeting,
        public readonly array $reasons,
        public readonly string $retryOn,
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
        $mail = (new MailMessage)
            ->subject('La eliminación de una cuenta de AulaX se aplazó')
            ->greeting($this->greeting)
            ->line('El cierre de la cuenta se aplazó porque todavía:');

        foreach ($this->reasons as $reason) {
            $mail->line("- {$reason}");
        }

        return $mail->line("Lo volveremos a intentar el {$this->retryOn}.");
    }
}
