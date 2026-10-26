<?php

declare(strict_types=1);

namespace AulaX\Audit\Application\PlatformLog;

use AulaX\Audit\Application\ListEntries\AuditLogPage;

/**
 * The platform log, newest first, fifty entries per page (SPEC-014
 * PLT-R07.2; closes SPEC-005 D2). Only the platform panel asks for it (an
 * architecture test), and it checks who asks.
 */
final readonly class ListPlatformEntriesQuery
{
    private const int PER_PAGE = 50;

    public function __construct(private PlatformAuditLog $log) {}

    public function __invoke(PlatformLogFilter $filter, int $page): AuditLogPage
    {
        return $this->log->page($filter, max(1, $page), self::PER_PAGE);
    }
}
