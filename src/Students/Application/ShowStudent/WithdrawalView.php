<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ShowStudent;

final readonly class WithdrawalView
{
    public function __construct(
        public string $withdrawnOn,
        public string $reason,
        public ?string $note,
        public ?string $reinstatedOn,
    ) {}
}
