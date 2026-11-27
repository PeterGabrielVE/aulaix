<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for a request the open institution can not see or answer:
 * for it, that request does not exist (ADR 0008).
 */
final class TransferNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos esa solicitud de traslado.');
    }
}
