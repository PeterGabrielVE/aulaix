<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure\Persistence;

use AulaX\Enrollment\Application\Port\EnrollmentList;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the enrollments (A-08), straight from the table of the open
 * institution (Row Level Security filters every query).
 */
final class EloquentEnrollmentList implements EnrollmentList
{
    public function currentCountsOf(int $schoolYearId): array
    {
        return DB::table('enrollments')
            ->where('school_year_id', $schoolYearId)
            ->whereNull('ends_on')
            ->groupBy('section_id')
            ->orderBy('section_id')
            ->selectRaw('section_id, count(*) as enrolled')
            ->pluck('enrolled', 'section_id')
            ->map(fn (mixed $count) => (int) $count)
            ->all();
    }

    public function currentIn(int $sectionId): array
    {
        return DB::table('enrollments')
            ->where('section_id', $sectionId)
            ->whereNull('ends_on')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => EnrollmentRows::toDomain($row))
            ->all();
    }

    public function currentStudentsOf(int $schoolYearId): array
    {
        return DB::table('enrollments')
            ->where('school_year_id', $schoolYearId)
            ->whereNull('ends_on')
            ->orderBy('student_id')
            ->pluck('student_id')
            ->map(fn (mixed $id) => (int) $id)
            ->all();
    }

    public function historyOf(int $studentId): array
    {
        return DB::table('enrollments')
            ->where('student_id', $studentId)
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row) => EnrollmentRows::toDomain($row))
            ->all();
    }
}
