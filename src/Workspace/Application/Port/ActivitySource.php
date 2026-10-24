<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\Port;

/**
 * The latest entries of the institution's audit log, from Audit.
 */
interface ActivitySource
{
    /**
     * @return list<array{occurredAt: string, actor: ?string, event: string}> Newest first.
     */
    public function recent(int $limit): array;
}
