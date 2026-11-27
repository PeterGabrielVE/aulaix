<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\ConsolidationPreviews;
use AulaX\Platform\Application\Port\OperatorSession;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

/**
 * Keeps the single active consolidation preview in the operator's panel
 * session (SPEC-017 RSP-R05), bound to the operator that generated it.
 * Expires with the session or after 10 minutes.
 */
final readonly class SessionConsolidationPreviews implements ConsolidationPreviews
{
    public const string KEY = 'platform.consolidation_preview_record';

    public function __construct(
        private Session $session,
        private OperatorSession $operatorSession,
    ) {}

    public function remember(array $accountIds, string $decision, DateTimeImmutable $at): void
    {
        $operatorId = $this->operatorSession->current()?->value;

        if ($operatorId === null) {
            return;
        }

        $this->session->put(self::KEY, [
            'operator_id' => $operatorId,
            'accounts' => self::normalize($accountIds),
            'decision' => $decision,
            'at' => $at->getTimestamp(),
        ]);
    }

    public function matches(array $accountIds, string $decision, DateTimeImmutable $now, int $maxAgeSeconds): bool
    {
        $operatorId = $this->operatorSession->current()?->value;
        $stored = $this->session->get(self::KEY);

        if ($operatorId === null
            || ! is_array($stored)
            || ! isset($stored['operator_id'], $stored['accounts'], $stored['decision'], $stored['at'])
            || $stored['operator_id'] !== $operatorId
            || ! is_array($stored['accounts'])
            || ! is_string($stored['decision'])
            || ! is_int($stored['at'])) {
            return false;
        }

        $age = $now->getTimestamp() - $stored['at'];

        return $age >= 0
            && $age <= $maxAgeSeconds
            && $stored['decision'] === $decision
            && $stored['accounts'] === self::normalize($accountIds);
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @param  list<int>  $accountIds
     * @return list<int>
     */
    private static function normalize(array $accountIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $accountIds)));
        sort($ids);

        return $ids;
    }
}
