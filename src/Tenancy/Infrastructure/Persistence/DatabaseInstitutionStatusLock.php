<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Shared\Infrastructure\AdvisoryLockKeys;
use AulaX\Tenancy\Application\InstitutionStatusLock;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/**
 * PostgreSQL transaction-level advisory lock on an institution's status
 * (ADR 0014, SPEC-017 RSP-R08.1, R08.5). Shared locks allow concurrent
 * transfers/jobs without waiting on each other; exclusive locks wait for
 * in-flight writes before deactivating or reactivating the institution.
 */
final readonly class DatabaseInstitutionStatusLock implements InstitutionStatusLock
{
    public function __construct(private ConnectionInterface $connection) {}

    public function shared(InstitutionId $id): void
    {
        $this->assertInTransaction();
        $this->connection->select(
            'SELECT pg_advisory_xact_lock_shared(?, ?)',
            [AdvisoryLockKeys::INSTITUTION_STATUS, $id->value],
        );
    }

    public function exclusive(InstitutionId $id): void
    {
        $this->assertInTransaction();
        $this->connection->select(
            'SELECT pg_advisory_xact_lock(?, ?)',
            [AdvisoryLockKeys::INSTITUTION_STATUS, $id->value],
        );
    }

    private function assertInTransaction(): void
    {
        if ($this->connection->transactionLevel() === 0) {
            throw new LogicException('InstitutionStatusLock requires an active database transaction.');
        }
    }
}
