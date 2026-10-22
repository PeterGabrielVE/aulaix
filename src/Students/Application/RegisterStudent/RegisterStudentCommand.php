<?php

declare(strict_types=1);

namespace AulaX\Students\Application\RegisterStudent;

use AulaX\Students\Application\StudentForm\StudentFields;

/**
 * A new student of the open institution: the form and its health data.
 */
final readonly class RegisterStudentCommand
{
    public function __construct(
        public StudentFields $student,
        public ?string $allergies,
        public ?string $medicalConditions,
        public ?string $disability,
    ) {}
}
