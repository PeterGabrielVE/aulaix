<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\Enrollment\Application\Port\CalendarFacts;
use DateTimeImmutable;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * The school years a test sets up, and whether the calendar lock was taken
 * inside the transaction (ADR 0007).
 */
final class FakeCalendarFacts implements CalendarFacts
{
    /** @var array<int, array{status: string, start: string, lastTermClosed: bool}> */
    public array $years = [];

    public int $lockedInsideTransaction = 0;

    public function __construct(private readonly InlineTransactionManager $transactions) {}

    public function year(int $id, int $start, string $status = 'active', bool $lastTermClosed = false): void
    {
        $this->years[$id] = ['status' => $status, 'start' => "{$start}-09-16", 'lastTermClosed' => $lastTermClosed];
    }

    public function statusOf(int $schoolYearId): ?string
    {
        return $this->years[$schoolYearId]['status'] ?? null;
    }

    public function startOf(int $schoolYearId): ?DateTimeImmutable
    {
        return isset($this->years[$schoolYearId]) ? new DateTimeImmutable($this->years[$schoolYearId]['start']) : null;
    }

    public function activeYear(): ?int
    {
        foreach ($this->years as $id => $year) {
            if ($year['status'] === 'active') {
                return $id;
            }
        }

        return null;
    }

    public function years(): array
    {
        $years = [];

        foreach ($this->years as $id => $year) {
            $start = (int) substr($year['start'], 0, 4);
            $years[] = ['id' => $id, 'name' => $start.'-'.($start + 1), 'status' => $year['status']];
        }

        usort($years, fn (array $a, array $b) => $b['name'] <=> $a['name']);

        return $years;
    }

    public function nextOf(int $schoolYearId): ?int
    {
        $start = $this->years[$schoolYearId]['start'] ?? null;
        $later = array_filter($this->years, fn (array $year) => $start !== null && $year['start'] > $start);
        uasort($later, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        return array_key_first($later);
    }

    public function lastTermClosed(int $schoolYearId): bool
    {
        return $this->years[$schoolYearId]['lastTermClosed'] ?? false;
    }

    public function lock(): void
    {
        if ($this->transactions->inTransaction) {
            $this->lockedInsideTransaction++;
        }
    }
}
