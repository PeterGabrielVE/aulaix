<?php

declare(strict_types=1);

namespace AulaX\Platform\Domain;

/**
 * Pending until the invitation is accepted; only an active operator enters.
 */
enum OperatorStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
}
