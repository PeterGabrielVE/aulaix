<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

/**
 * Support's decision on a case marked for review (IDG-R11.2).
 */
enum ConsolidationDecision: string
{
    case Consolidate = 'consolidate';
    case Separate = 'separate';
}
