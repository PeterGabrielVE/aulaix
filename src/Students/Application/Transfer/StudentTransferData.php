<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;

/**
 * The record a transfer takes to the destination (EST-R07.5): the student's
 * data, identification, health data and guardians.
 */
final readonly class StudentTransferData
{
    /**
     * @param  list<TransferredGuardian>  $guardians
     */
    public function __construct(
        public PersonalData $personalData,
        public StudentIdentification $identification,
        public HealthRecord $health,
        public array $guardians,
        public string $originName,
    ) {}
}
