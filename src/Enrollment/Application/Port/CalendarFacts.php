<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

use DateTimeImmutable;

/**
 * The school years of the open institution, as AcademicCalendar tells them
 * (A-06), and its calendar lock (ADR 0007), which every enrollment write
 * takes: it serializes them with the changes of capacity, the removal of
 * sections and the closing of the year.
 */
interface CalendarFacts
{
    /**
     * planned, active or closed; null for a year the institution does not have.
     */
    public function statusOf(int $schoolYearId): ?string;

    public function startOf(int $schoolYearId): ?DateTimeImmutable;

    public function activeYear(): ?int;

    /**
     * @return list<array{id: int, name: string, status: string}> Newest first.
     */
    public function years(): array;

    public function nextOf(int $schoolYearId): ?int;

    public function lastTermClosed(int $schoolYearId): bool;

    /**
     * Taken inside the use case's transaction, released with it.
     */
    public function lock(): void;
}
