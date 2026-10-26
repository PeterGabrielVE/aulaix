<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

use AulaX\Shared\Domain\NotFound;

/**
 * Expired, used, revoked or unknown: one answer for all, so a link tells
 * nothing about itself (SPEC-014 V4).
 */
final class OperatorInvitationRejected extends NotFound
{
    public static function create(): self
    {
        return new self('El enlace no es válido o ha caducado.');
    }
}
