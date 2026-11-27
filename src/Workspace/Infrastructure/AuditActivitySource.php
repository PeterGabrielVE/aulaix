<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\Audit\Application\ListEntries\AuditEntryView;
use AulaX\Audit\Application\ListEntries\ListAuditEntriesHandler;
use AulaX\Audit\Application\ListEntries\ListAuditEntriesQuery;
use AulaX\Workspace\Application\Port\ActivitySource;
use DateTimeInterface;

/**
 * Through Audit's public query (A-06): the first page of the log, newest
 * first. Only when, who and what: never the changed values.
 */
final readonly class AuditActivitySource implements ActivitySource
{
    public function __construct(private ListAuditEntriesHandler $entries) {}

    public function recent(int $limit): array
    {
        return array_map(fn (AuditEntryView $entry) => [
            'occurredAt' => $entry->occurredAt->format(DateTimeInterface::ATOM),
            'actor' => $entry->actorName,
            'event' => $entry->eventType,
        ], array_slice(($this->entries)(new ListAuditEntriesQuery)->entries, 0, $limit));
    }
}
