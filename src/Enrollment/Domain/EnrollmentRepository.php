<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * The enrollments of the open institution (Row Level Security, S-01).
 */
interface EnrollmentRepository
{
    public function byId(EnrollmentId $id): ?Enrollment;

    /**
     * The current enrollment of a student in a school year, if any.
     */
    public function currentOf(int $studentId, int $schoolYearId): ?Enrollment;

    /**
     * Every current enrollment of a student, of any school year.
     *
     * @return list<Enrollment>
     */
    public function currentOfStudent(int $studentId): array;

    /**
     * How many current enrollments a section has.
     */
    public function currentCountIn(int $sectionId): int;

    /**
     * Whether a section has any enrollment, current, ended or annulled.
     */
    public function hasAnyIn(int $sectionId): bool;

    /**
     * @throws AlreadyEnrolled When the student already has a current
     *                         enrollment in that school year.
     */
    public function save(Enrollment $enrollment): EnrollmentId;
}
