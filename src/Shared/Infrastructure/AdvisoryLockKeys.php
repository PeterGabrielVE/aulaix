<?php

declare(strict_types=1);

namespace AulaX\Shared\Infrastructure;

/**
 * Shared namespace for PostgreSQL transaction-level advisory lock keys
 * (ADR 0007, ADR 0014). Each constant is the first int4 key of
 * pg_advisory_xact_lock(class, object), keeping every lock family distinct.
 */
final class AdvisoryLockKeys
{
    /**
     * ADR 0007: school year / enrollment operations scoped to an institution id.
     */
    public const int CALENDAR = 7007;

    /**
     * ADR 0014: institution active/inactive status transitions and cross-tenant writes.
     */
    public const int INSTITUTION_STATUS = 1001;

    /**
     * ADR 0014: platform operator login, confirmation, MFA and management mutations.
     */
    public const int OPERATOR = 1014;

    /**
     * ADR 0014: platform operator roster changes guarding the last active SuperAdmin.
     */
    public const int OPERATOR_ROSTER = 1015;

    /**
     * ADR 0014: account consolidation cases keyed by hashtext(lower(email)).
     */
    public const int CONSOLIDATION = 4011;
}
