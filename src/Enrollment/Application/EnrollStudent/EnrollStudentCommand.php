<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\EnrollStudent;

final readonly class EnrollStudentCommand
{
    /**
     * @param  ?string  $date  Y-m-d
     * @param  ?string  $ageExceptionJustification  Only when the age is out of range.
     * @param  int  $actorId  The account signed in: who enrolls and, if any, approves the exception.
     */
    public function __construct(
        public int $studentId,
        public int $schoolYearId,
        public int $sectionId,
        public ?string $date,
        public ?string $overCapacityReason,
        public ?string $ageExceptionJustification,
        public int $actorId,
    ) {}
}
