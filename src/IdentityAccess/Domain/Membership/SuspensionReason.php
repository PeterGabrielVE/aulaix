<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Membership;

/**
 * Why a membership is suspended. Cancelling an account deletion reactivates
 * only the memberships suspended for that reason (IDG-R08.9).
 */
enum SuspensionReason: string
{
    case Institution = 'institution';
    case Security = 'security';
    case AccountDeletion = 'account_deletion';
}
