<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

use DateTimeImmutable;

/**
 * Remembers the operator's last consolidation preview in their panel session
 * so the server enforces a recent matching preview before applying a
 * resolution (SPEC-017 RSP-R05.1 – R05.3).
 */
interface ConsolidationPreviews
{
    /**
     * @param  list<int>  $accountIds
     */
    public function remember(array $accountIds, string $decision, DateTimeImmutable $at): void;

    /**
     * @param  list<int>  $accountIds
     */
    public function matches(array $accountIds, string $decision, DateTimeImmutable $now, int $maxAgeSeconds): bool;

    public function forget(): void;
}
