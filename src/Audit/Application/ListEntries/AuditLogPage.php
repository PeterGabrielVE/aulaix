<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\ListEntries;

final readonly class AuditLogPage
{
    /**
     * @param  list<AuditEntryView>  $entries  Newest first.
     */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
