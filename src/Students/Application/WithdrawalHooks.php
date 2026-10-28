<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Students\Application\Port\WithdrawalHook;
use DateTimeImmutable;

/**
 * Every registered WithdrawalHook, told together.
 */
final readonly class WithdrawalHooks
{
    /**
     * @param  iterable<WithdrawalHook>  $hooks
     */
    public function __construct(private iterable $hooks) {}

    public function studentWithdrawn(int $studentId, DateTimeImmutable $on, bool $transferred): void
    {
        foreach ($this->hooks as $hook) {
            $hook->studentWithdrawn($studentId, $on, $transferred);
        }
    }
}
