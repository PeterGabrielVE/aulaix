<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\Port;

/**
 * Students' figures and a guardian's own students, from Students.
 */
interface StudentSource
{
    public function activeStudents(): int;

    /**
     * @return array{transfers: int, corrections: int} Requests waiting for this institution's answer.
     */
    public function pendingRequests(): array;

    /**
     * @return array{students: list<array{id: int, name: string}>, pendingCorrections: int}
     */
    public function representedBy(int $accountId): array;
}
