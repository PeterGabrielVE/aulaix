<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure\Persistence;

use AulaX\Enrollment\Domain\AlreadyEnrolled;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The enrollments of the open institution, through the query builder (Row
 * Level Security filters every query). The unique index of current
 * enrollments turns into a conflict (MAT-NF03).
 */
final class EloquentEnrollmentRepository implements EnrollmentRepository
{
    private const ONE_CURRENT_PER_YEAR = 'enrollments_one_current_per_year';

    public function byId(EnrollmentId $id): ?Enrollment
    {
        $row = DB::table('enrollments')->where('id', $id->value)->first();

        return $row === null ? null : EnrollmentRows::toDomain($row);
    }

    public function currentOf(int $studentId, int $schoolYearId): ?Enrollment
    {
        $row = DB::table('enrollments')
            ->where('student_id', $studentId)
            ->where('school_year_id', $schoolYearId)
            ->whereNull('ends_on')
            ->first();

        return $row === null ? null : EnrollmentRows::toDomain($row);
    }

    public function currentOfStudent(int $studentId): array
    {
        return DB::table('enrollments')
            ->where('student_id', $studentId)
            ->whereNull('ends_on')
            ->orderBy('starts_on')
            ->get()
            ->map(fn (object $row) => EnrollmentRows::toDomain($row))
            ->all();
    }

    public function currentCountIn(int $sectionId): int
    {
        return DB::table('enrollments')->where('section_id', $sectionId)->whereNull('ends_on')->count();
    }

    public function hasAnyIn(int $sectionId): bool
    {
        return DB::table('enrollments')->where('section_id', $sectionId)->exists();
    }

    public function save(Enrollment $enrollment): EnrollmentId
    {
        $row = EnrollmentRows::toRow($enrollment);

        try {
            if ($enrollment->id() !== null) {
                DB::table('enrollments')->where('id', $enrollment->id()->value)->update([...$row, 'updated_at' => now()]);

                return $enrollment->id();
            }

            $id = EnrollmentId::fromInt((int) DB::table('enrollments')->insertGetId([...$row, 'created_at' => now(), 'updated_at' => now()]));
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), self::ONE_CURRENT_PER_YEAR)) {
                throw AlreadyEnrolled::in(null, null);
            }

            throw $exception;
        }

        $enrollment->identify($id);

        return $id;
    }
}
