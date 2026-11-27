<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure;

use AulaX\Platform\Application\Port\ConsolidationPreviews;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;

/**
 * Keeps the single active consolidation preview in the operator's panel
 * session (SPEC-017 RSP-R05). Expires with the session or after 10 minutes.
 */
final readonly class SessionConsolidationPreviews implements ConsolidationPreviews
{
    public const string KEY = 'platform.consolidation_preview_record';

    public function __construct(private Session $session) {}

    public function remember(array $accountIds, string $decision, DateTimeImmutable $at): void
    {
        $this->session->put(self::KEY, [
            'accounts' => self::normalize($accountIds),
            'decision' => $decision,
            'at' => $at->getTimestamp(),
        ]);
    }

    public function matches(array $accountIds, string $decision, DateTimeImmutable $now, int $maxAgeSeconds): bool
    {
        $stored = $this->session->get(self::KEY);

        if (! is_array($stored)
            || ! isset($stored['accounts'], $stored['decision'], $stored['at'])
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
