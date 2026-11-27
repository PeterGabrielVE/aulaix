<?php

declare(strict_types=1);

namespace AulaX\Students\Application\UpdateStudent;

use AulaX\Students\Application\StudentForm\StudentFields;

final readonly class UpdateStudentCommand
{
    public function __construct(
        public int $studentId,
        public StudentFields $student,
    ) {}
}
