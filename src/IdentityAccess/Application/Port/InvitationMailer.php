<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Invitation\Invitation;
use AulaX\Tenancy\Application\TenantContext;

/**
 * Emails an invitation's link. The plain token only exists in that email:
 * the invitation keeps its hash (IDG-NF04).
 */
interface InvitationMailer
{
    public function send(Invitation $invitation, #[\SensitiveParameter] string $token, TenantContext $institution): void;
}
