<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Shared\Domain\DomainException;

/**
 * A handoff token that is forged, expired, already used, meant for another
 * institution, or for someone no longer active there. Answered with the
 * central login, without saying which (design: Errores).
 */
final class HandoffRejected extends DomainException
{
    public static function create(): self
    {
        return new self('The handoff was rejected.');
    }
}
