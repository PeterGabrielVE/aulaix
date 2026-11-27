<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Notifications;

use AulaX\Platform\Application\Port\OperatorMailer;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use Illuminate\Support\Facades\Notification;

final class NotificationOperatorMailer implements OperatorMailer
{
    public function sendLink(OperatorEmail $email, string $name, #[\SensitiveParameter] string $token, InvitationPurpose $purpose): void
    {
        Notification::route('mail', $email->value)->notify(new OperatorLinkMail($name, $token, $purpose));
    }

    public function operatorBlocked(Operator $blocked, array $failures, array $superAdmins): void
    {
        Notification::route('mail', $blocked->email->value)
            ->notify(new OperatorBlockedMail($blocked->name, $blocked->email->value, $failures, forSelf: true));

        $notified = [$blocked->email->value => true];

        foreach ($superAdmins as $adminEmail) {
            if (isset($notified[$adminEmail->value])) {
                continue;
            }

            $notified[$adminEmail->value] = true;
            Notification::route('mail', $adminEmail->value)
                ->notify(new OperatorBlockedMail($blocked->name, $blocked->email->value, $failures, forSelf: false));
        }
    }
}
