<?php

declare(strict_types=1);

namespace AulaX\Students\Application\WithdrawStudent;

final readonly class WithdrawStudentCommand
{
    public function __construct(
        public int $studentId,
        public ?string $withdrawnOn,
        public ?string $reason,
        public ?string $note,
    ) {}
}
