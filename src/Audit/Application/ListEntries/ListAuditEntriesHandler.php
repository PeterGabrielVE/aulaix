<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\ListEntries;

/**
 * The institution's audit log, newest first, fifty entries per page
 * (AUD-R05.1), optionally filtered (AUD-R05.2).
 */
final readonly class ListAuditEntriesHandler
{
    private const PER_PAGE = 50;

    public function __construct(private AuditLog $log) {}

    public function __invoke(ListAuditEntriesQuery $query): AuditLogPage
    {
        return $this->log->page(
            new AuditLogFilter(
                $query->from,
                $query->to,
                $query->actorId,
                $query->eventType,
                $query->subjectType,
                $query->subjectId,
            ),
            max(1, $query->page),
            self::PER_PAGE,
        );
    }
}
