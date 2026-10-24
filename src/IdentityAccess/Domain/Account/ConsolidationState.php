<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

/**
 * Why a historical account shares its email with others (ADR 0006 B). A
 * regular account has no consolidation state at all.
 */
enum ConsolidationState: string
{
    /** Same email and, apparently, the same person: the owner can consolidate after verifying the email. */
    case Pending = 'pending';

    /** Same email but different names: only support may resolve it (IDG-R09.5, IDG-R11). */
    case Review = 'review';

    /** Reviewed by support: different people sharing an email, never to be merged (IDG-R11.2). */
    case Separated = 'separated';
}
