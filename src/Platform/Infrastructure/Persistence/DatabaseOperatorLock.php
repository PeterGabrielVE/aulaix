<?php

declare(strict_types=1);

namespace AulaX\Platform\Infrastructure\Persistence;

use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Shared\Infrastructure\AdvisoryLockKeys;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/**
 * PostgreSQL transaction-level advisory locks for platform operators
 * (ADR 0014, SPEC-017 RSP-R01, R02, R03). Released automatically on commit or
 * rollback, so they are safe with PgBouncer in transaction mode (ADR 0011).
 */
final readonly class DatabaseOperatorLock implements OperatorLock
{
    public function __construct(private ConnectionInterface $connection) {}

    public function lockOperator(OperatorId $id): void
    {
        $this->assertInTransaction();
        $this->connection->select('SELECT pg_advisory_xact_lock(?, ?)', [AdvisoryLockKeys::OPERATOR, $id->value]);
    }

    public function lockRoster(): void
    {
        $this->assertInTransaction();
        $this->connection->select('SELECT pg_advisory_xact_lock(?, ?)', [AdvisoryLockKeys::OPERATOR_ROSTER, 0]);
    }

    private function assertInTransaction(): void
    {
        if ($this->connection->transactionLevel() === 0) {
            throw new LogicException('OperatorLock requires an active database transaction.');
        }
    }
}
