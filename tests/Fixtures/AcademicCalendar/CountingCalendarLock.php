<?php

namespace Tests\Fixtures\AcademicCalendar;

use AulaX\AcademicCalendar\Application\CalendarLock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * Remembers whether the lock was taken inside the transaction (ADR 0007).
 */
final class CountingCalendarLock implements CalendarLock
{
    public int $takenInsideTransaction = 0;

    public function __construct(private readonly InlineTransactionManager $transactions) {}

    public function lockInstitutionCalendar(): void
    {
        if ($this->transactions->inTransaction) {
            $this->takenInsideTransaction++;
        }
    }
}
