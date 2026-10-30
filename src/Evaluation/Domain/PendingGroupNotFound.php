<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\NotFound;

/**
 * A group of pending subjects the institution does not have, or that nobody
 * owes this year.
 */
final class PendingGroupNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos ese grupo de pendientes.');
    }
}
