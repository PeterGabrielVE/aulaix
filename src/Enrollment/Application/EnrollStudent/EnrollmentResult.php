<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\EnrollStudent;

final readonly class EnrollmentResult
{
    /**
     * @param  ?string  $ageWarning  When the age is not the regular one of the grade (MAT-R03.2).
     */
    public function __construct(
        public int $enrollmentId,
        public ?string $ageWarning,
    ) {}
}
