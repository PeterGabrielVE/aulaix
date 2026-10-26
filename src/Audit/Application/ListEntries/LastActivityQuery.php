<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\ListEntries;

use AulaX\Shared\Application\PlatformReadScope;
use AulaX\Shared\Domain\AuthorizationDenied;
use DateTimeImmutable;

/**
 * For the platform (SPEC-014 PLT-R04.3, V2): when the open institution
 * last recorded anything in its log. Only inside the platform's bounded
 * read.
 */
final readonly class LastActivityQuery
{
    public function __construct(private AuditLog $log) {}

    /**
     * @throws AuthorizationDenied
     */
    public function __invoke(): ?DateTimeImmutable
    {
        PlatformReadScope::ensureActive();

        return $this->log->page(new AuditLogFilter, 1, 1)->entries[0]->occurredAt ?? null;
    }
}
