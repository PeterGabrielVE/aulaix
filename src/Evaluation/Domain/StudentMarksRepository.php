<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The literals and reports of the open institution (Row Level Security,
 * S-01).
 */
interface StudentMarksRepository
{
    /**
     * @param  list<int>  $studentIds
     * @return array<int, StudentMarks> Student id => its marks, empty ones included.
     */
    public function forStudents(int $schoolYearId, array $studentIds): array;

    public function saveMarks(StudentMarks $marks): void;
}
