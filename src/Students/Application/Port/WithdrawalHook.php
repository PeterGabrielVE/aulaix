<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Port;

use DateTimeImmutable;

/**
 * An extension point for the modules that build on students (ADR 0012):
 * SPEC-010 ends the enrollments of a withdrawn or transferred student. Told
 * inside the withdrawal's transaction: if it fails, the withdrawal does
 * not happen (MAT-R05.5). Until a module registers one, nothing is told.
 */
interface WithdrawalHook
{
    /**
     * The container tag a module puts its hook under.
     */
    public const string TAG = 'students.withdrawal_hooks';

    public function studentWithdrawn(int $studentId, DateTimeImmutable $on, bool $transferred): void;
}
