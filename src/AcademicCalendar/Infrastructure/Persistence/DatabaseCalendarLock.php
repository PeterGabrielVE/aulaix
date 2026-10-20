<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Infrastructure\Persistence;

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\Tenancy\Application\CurrentTenant;
use Illuminate\Database\ConnectionInterface;
use LogicException;

/**
 * A PostgreSQL transaction-level advisory lock keyed by the institution
 * (ADR 0007): released by itself when the transaction ends.
 */
final readonly class DatabaseCalendarLock implements CalendarLock
{
    /**
     * First half of the lock key: this kind of lock, apart from any other
     * advisory lock keyed by an institution id.
     */
    private const NAMESPACE = 7007;

    public function __construct(
        private ConnectionInterface $connection,
        private CurrentTenant $tenant,
    ) {}

    public function lockInstitutionCalendar(): void
    {
        $institutionId = $this->tenant->institutionId()?->value
            ?? throw new LogicException('The calendar belongs to an institution: activate one first.');

        $this->connection->select('SELECT pg_advisory_xact_lock(?, ?)', [self::NAMESPACE, $institutionId]);
    }
}
