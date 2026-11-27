<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The logged-in account is not the one the invitation was sent to
 * (IDG-R04.6). Rendered as 403.
 */
final class InvitationEmailMismatch extends AuthorizationDenied
{
    public static function create(): self
    {
        return new self('Esta invitación es para otro correo electrónico.');
    }
}
