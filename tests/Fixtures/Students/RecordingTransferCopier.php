<?php

namespace Tests\Fixtures\Students;

use AulaX\Shared\Domain\Conflict;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\Port\TransferCopier;
use AulaX\Students\Application\Transfer\StudentTransferData;

/**
 * Keeps what it was asked to copy, or fails as when the destination already
 * has the student.
 */
final class RecordingTransferCopier implements TransferCopier
{
    /** @var list<array{destination: InstitutionId, data: StudentTransferData}> */
    public array $copies = [];

    public bool $destinationHasTheStudent = false;

    public function copyTo(InstitutionId $destination, StudentTransferData $data): void
    {
        if ($this->destinationHasTheStudent) {
            throw new Conflict('Ya hay un estudiante con esa identificación en el plantel.');
        }

        $this->copies[] = ['destination' => $destination, 'data' => $data];
    }
}
