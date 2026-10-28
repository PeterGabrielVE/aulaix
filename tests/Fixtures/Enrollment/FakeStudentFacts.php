<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Students\Application\Enrollable\EnrollableStudent;

/**
 * The students a test puts in the institution.
 */
final class FakeStudentFacts implements StudentFacts
{
    /** @var array<int, EnrollableStudent> */
    public array $students = [];

    public function add(int $id, string $birthDate = '2018-03-10', bool $active = true, string $name = ''): void
    {
        $this->students[$id] = new EnrollableStudent($id, $name === '' ? "Estudiante {$id}" : $name, "V3000000{$id}", $birthDate, $active);
    }

    public function byIds(array $ids): array
    {
        return array_intersect_key($this->students, array_flip($ids));
    }

    public function active(): array
    {
        return array_values(array_filter($this->students, fn (EnrollableStudent $student) => $student->active));
    }
}
