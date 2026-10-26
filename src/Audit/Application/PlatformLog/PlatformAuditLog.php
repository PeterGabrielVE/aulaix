<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\PlatformLog;

use AulaX\Audit\Application\ListEntries\AuditLogPage;

/**
 * Read side of the platform log (constitution A-08): never an institution's
 * (AUD-R04.1).
 */
interface PlatformAuditLog
{
    /**
     * Entries matching the filter, newest first.
     */
    public function page(PlatformLogFilter $filter, int $page, int $perPage): AuditLogPage;
}
