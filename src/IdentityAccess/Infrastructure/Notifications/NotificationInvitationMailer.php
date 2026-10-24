<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Notifications;

use AulaX\IdentityAccess\Application\Port\InvitationMailer;
use AulaX\IdentityAccess\Domain\Invitation\Invitation;
use AulaX\Tenancy\Application\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sends the invitation as an on-demand notification: the invitee may have
 * no account yet, and if they have one, the institution must not learn it.
 */
final class NotificationInvitationMailer implements InvitationMailer
{
    public function send(Invitation $invitation, #[\SensitiveParameter] string $token, TenantContext $institution): void
    {
        $url = URL::route('invitations.accept', [
            'subdomain' => $institution->subdomain,
            'token' => $token,
            'email' => $invitation->email->value,
        ]);

        Notification::route('mail', $invitation->email->value)->notify(new InvitationMail(
            $invitation->name->value,
            $institution->name,
            $url,
            Invitation::LIFETIME_DAYS,
        ));
    }
}
