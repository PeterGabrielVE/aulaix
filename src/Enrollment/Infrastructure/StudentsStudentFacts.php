<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure;

use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Students\Application\Enrollable\EnrollableStudentsQuery;

/**
 * The students, through Students' public query (A-06).
 */
final readonly class StudentsStudentFacts implements StudentFacts
{
    public function __construct(private EnrollableStudentsQuery $students) {}

    public function byIds(array $ids): array
    {
        return $this->students->byIds($ids);
    }

    public function active(): array
    {
        return $this->students->active();
    }
}
