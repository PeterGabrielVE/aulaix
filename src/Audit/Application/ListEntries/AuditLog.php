<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\ListEntries;

/**
 * Read side of the active institution's audit log (constitution A-08).
 */
interface AuditLog
{
    /**
     * Entries matching the filter, newest first.
     */
    public function page(AuditLogFilter $filter, int $page, int $perPage): AuditLogPage;

    /**
     * Null when the entry does not exist or belongs to another institution.
     */
    public function find(int $id): ?AuditEntryView;
}
