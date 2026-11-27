<?php

namespace Tests\Fixtures\Audit;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * Keeps the recorded events, noting whether each came inside the
 * transaction (ADR 0005).
 */
final class RecordingAuditTrail implements AuditTrail
{
    /** @var list<array{event: AuditEvent, inTransaction: bool}> */
    public array $recorded = [];

    public function __construct(private readonly ?TransactionManager $transactions = null) {}

    public function record(AuditEvent $event): void
    {
        $this->recorded[] = ['event' => $event, 'inTransaction' => $this->transactions instanceof InlineTransactionManager && $this->transactions->inTransaction];
    }

    public function recordForPlatform(AuditEvent $event): void
    {
        $this->record($event);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_map(fn (array $entry) => $entry['event']->type, $this->recorded);
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function changesOf(int $index): array
    {
        $changes = [];

        foreach ($this->recorded[$index]['event']->changes as $change) {
            $changes[$change->field] = ['from' => $change->from, 'to' => $change->to];
        }

        return $changes;
    }
}
