<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Invitation;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The same answer whatever the reason (wrong token, expired, accepted or
 * cancelled), so a link reveals nothing about the invitation behind it.
 */
final class InvitationNotUsable extends BusinessRuleViolation
{
    public static function create(): self
    {
        return self::onField('email', 'Esta invitación no es válida o ya caducó.');
    }
}
