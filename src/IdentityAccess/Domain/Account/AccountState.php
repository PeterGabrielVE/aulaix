<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

/**
 * Lifecycle of the global account (SPEC-004 IDG-R08). "deleted" is only
 * reached once legal retention rules exist (dependency D1).
 */
enum AccountState: string
{
    case Active = 'active';
    case PendingDeletion = 'pending_deletion';
    case Closed = 'closed';
    case Deleted = 'deleted';
}
