<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure\Persistence;

use AulaX\IdentityAccess\Application\Consolidation\ConsolidationLock;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\Shared\Infrastructure\AdvisoryLockKeys;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/**
 * PostgreSQL transaction-level advisory lock keyed by hashtext(lower(email))
 * (ADR 0014, SPEC-017 RSP-R04). Released automatically on commit or rollback.
 */
final readonly class DatabaseConsolidationLock implements ConsolidationLock
{
    public function __construct(private ConnectionInterface $connection) {}

    public function lockEmail(EmailAddress $email): void
    {
        if ($this->connection->transactionLevel() === 0) {
            throw new LogicException('ConsolidationLock requires an active database transaction.');
        }

        $this->connection->select(
            'SELECT pg_advisory_xact_lock(?, hashtext(lower(?)))',
            [AdvisoryLockKeys::CONSOLIDATION, $email->value],
        );
    }
}
