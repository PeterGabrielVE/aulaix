<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Shared\Domain\InstitutionId;
use RuntimeException;

/**
 * Thrown by InstitutionContexts::writableOf() when an institution does not
 * exist or is inactive (SPEC-017 RSP-R08.1, R08.5). Caught by callers
 * (ApproveTransferHandler, RunInInstitution); never mapped to a 4xx directly.
 */
final class InstitutionUnavailable extends RuntimeException
{
    public static function forId(InstitutionId $id): self
    {
        return new self("Institution {$id->value} is unavailable for writes.");
    }
}
