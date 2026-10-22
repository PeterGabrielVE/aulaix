<?php

namespace Tests\Fixtures\Students;

use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Student\StudentStatus;

/**
 * The students of one institution, kept in memory.
 */
final class InMemoryStudentRepository implements StudentRepository
{
    /** @var array<int, Student> */
    public array $students = [];

    private int $nextId = 1;

    public function byId(StudentId $id): ?Student
    {
        return isset($this->students[$id->value]) ? clone $this->students[$id->value] : null;
    }

    public function save(Student $student): StudentId
    {
        $id = $student->id() ?? StudentId::fromInt($this->nextId++);
        $student->identify($id);
        $this->students[$id->value] = clone $student;

        return $id;
    }

    public function activeByIdentification(StudentIdentification $identification): ?Student
    {
        foreach ($this->students as $student) {
            if ($student->identification()->value() === $identification->value() && $student->status() === StudentStatus::Active) {
                return clone $student;
            }
        }

        return null;
    }

    public function isIdentificationTaken(StudentIdentification $identification, ?StudentId $except): bool
    {
        foreach ($this->students as $id => $student) {
            if ($id !== $except?->value && $student->identification()->value() === $identification->value()) {
                return true;
            }
        }

        return false;
    }
}
