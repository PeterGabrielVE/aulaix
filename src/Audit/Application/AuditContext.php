<?php

declare(strict_types=1);

namespace AulaX\Audit\Application;

/**
 * Who is making changes in the current process, and from where. Resolved by
 * the infrastructure, so use cases only describe what changed.
 */
interface AuditContext
{
    /**
     * The authenticated account, or null for console and system changes.
     */
    public function actorId(): ?int;

    public function origin(): AuditOrigin;

    /**
     * For console commands run by a support operator (e.g. IDG-R11): the
     * changes that follow are attributed to them.
     */
    public function actAsConsoleOperator(string $operator): void;

    /**
     * For requests of the platform panel (SPEC-014): the changes and
     * queries that follow are attributed to that operator.
     */
    public function actAsPlatformOperator(int $operatorId): void;
}
