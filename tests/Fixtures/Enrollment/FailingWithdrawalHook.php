<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\Students\Application\Port\WithdrawalHook;
use DateTimeImmutable;
use RuntimeException;

/**
 * A module whose reaction to a withdrawal fails, for MAT-R05.5.
 */
final class FailingWithdrawalHook implements WithdrawalHook
{
    public function studentWithdrawn(int $studentId, DateTimeImmutable $on, bool $transferred): void
    {
        throw new RuntimeException('The reaction to the withdrawal failed.');
    }
}
