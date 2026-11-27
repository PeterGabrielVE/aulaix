<?php

declare(strict_types=1);

namespace AulaX\Audit\Infrastructure;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\Shared\Domain\Clock;
use DateTimeInterface;
use Illuminate\Database\Connection;
use LogicException;

/**
 * Inserts audit entries on the connection (and transaction) the audited
 * change is using (ADR 0005), so both commit or roll back together (AUD-R02).
 * institution_id is filled by the column's default from the RLS session, like
 * any institution-scoped table.
 */
final readonly class DatabaseAuditTrail implements AuditTrail
{
    public function __construct(
        private Connection $connection,
        private AuditContext $context,
        private Clock $clock,
    ) {}

    public function record(AuditEvent $event): void
    {
        $this->insert('audit_entries', $event);
    }

    public function recordForPlatform(AuditEvent $event): void
    {
        $this->insert('platform_audit_entries', $event);
    }

    private function insert(string $table, AuditEvent $event): void
    {
        if ($this->connection->transactionLevel() === 0) {
            throw new LogicException('Audit entries must be recorded inside the transaction of the change they describe.');
        }

        $origin = $this->context->origin();

        $this->connection->table($table)->insert([
            'occurred_at' => $this->clock->now()->format(DateTimeInterface::ATOM),
            'event_type' => $event->type,
            'subject_type' => $event->subject->type,
            'subject_id' => $event->subject->id,
            'actor_user_id' => $this->context->actorId(),
            'origin' => $origin->kind,
            'ip' => $origin->ip,
            'operator' => $origin->operator,
            'changes' => self::encodeChanges($event->changes),
        ]);
    }

    /**
     * @param  list<FieldChange>  $changes
     */
    private static function encodeChanges(array $changes): string
    {
        $encoded = [];

        foreach ($changes as $change) {
            $encoded[$change->field] = ['from' => $change->from, 'to' => $change->to];
        }

        return $encoded === [] ? '{}' : json_encode($encoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
