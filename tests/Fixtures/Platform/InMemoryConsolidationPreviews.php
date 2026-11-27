<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\ConsolidationPreviews;
use DateTimeImmutable;

/**
 * In-memory preview store for unit tests (SPEC-017 RSP-R05).
 */
final class InMemoryConsolidationPreviews implements ConsolidationPreviews
{
    /** @var ?array{accounts: list<int>, decision: string, at: int} */
    public ?array $stored = null;

    public function remember(array $accountIds, string $decision, DateTimeImmutable $at): void
    {
        $ids = array_values(array_unique(array_map('intval', $accountIds)));
        sort($ids);
        $this->stored = ['accounts' => $ids, 'decision' => $decision, 'at' => $at->getTimestamp()];
    }

    public function matches(array $accountIds, string $decision, DateTimeImmutable $now, int $maxAgeSeconds): bool
    {
        if ($this->stored === null) {
            return false;
        }

        $ids = array_values(array_unique(array_map('intval', $accountIds)));
        sort($ids);
        $age = $now->getTimestamp() - $this->stored['at'];

        return $age >= 0
            && $age <= $maxAgeSeconds
            && $this->stored['decision'] === $decision
            && $this->stored['accounts'] === $ids;
    }

    public function forget(): void
    {
        $this->stored = null;
    }
}
