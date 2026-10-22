<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use AulaX\Shared\Domain\Conflict;
use AulaX\Students\Domain\Identity\StudentIdentification;

/**
 * The students of the open institution (Row Level Security limits every
 * read and write to it).
 */
interface StudentRepository
{
    /**
     * With its guardianships, each carrying its guardian's document.
     */
    public function byId(StudentId $id): ?Student;

    /**
     * Saves the whole record: data, guardianships, withdrawals and former
     * cédulas escolares.
     *
     * @throws Conflict When a unique rule of the database breaks.
     */
    public function save(Student $student): StudentId;

    /**
     * Whether a student of the institution other than $except has this
     * identification (EST-R02.6).
     */
    public function isIdentificationTaken(StudentIdentification $identification, ?StudentId $except): bool;
}
