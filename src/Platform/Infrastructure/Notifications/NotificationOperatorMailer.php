<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Notifications;

use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorEmail;
use Illuminate\Support\Facades\Notification;

final class NotificationOperatorMailer implements OperatorMailer
{
    public function sendLink(OperatorEmail $email, string $name, #[\SensitiveParameter] string $token, InvitationPurpose $purpose): void
    {
        Notification::route('mail', $email->value)->notify(new OperatorLinkMail($name, $token, $purpose));
    }
}
