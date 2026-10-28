<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Enrollable;

use AulaX\Students\Application\StudentDirectory;

/**
 * The public API for SPEC-010: the students of the open institution as
 * enrolling needs them, by id or every active one, by family and given
 * names. Who may see them is the caller's check.
 */
final readonly class EnrollableStudentsQuery
{
    public function __construct(private StudentDirectory $students) {}

    /**
     * @param  list<int>  $ids
     * @return array<int, EnrollableStudent> By id; the ones it does not have are left out.
     */
    public function byIds(array $ids): array
    {
        $students = [];

        foreach ($this->students->enrollable(array_values(array_filter($ids, fn (int $id) => $id > 0))) as $student) {
            $students[$student->id] = $student;
        }

        return $students;
    }

    /**
     * @return list<EnrollableStudent>
     */
    public function active(): array
    {
        return $this->students->enrollable(null);
    }
}
