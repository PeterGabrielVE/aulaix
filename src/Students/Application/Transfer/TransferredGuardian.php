<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Student\Relationship;

/**
 * A guardian of a transferred student: its data, never its account
 * (EST-R07.8), and how it is linked.
 */
final readonly class TransferredGuardian
{
    public function __construct(
        public GuardianData $data,
        public Relationship $relationship,
        public bool $isLegal,
        public bool $isPrimary,
    ) {}
}
