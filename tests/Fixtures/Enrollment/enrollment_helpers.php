<?php

use Illuminate\Support\Facades\DB;

/**
 * Rows for the enrollment tests against PostgreSQL, in the active
 * institution (Row Level Security fills institution_id).
 */
function enrollmentYear(int $start = 2026, string $status = 'active'): int
{
    return DB::table('school_years')->insertGetId(['starts_on' => "{$start}-09-16", 'ends_on' => ($start + 1).'-07-15', 'status' => $status]);
}

function enrollmentSection(int $schoolYearId, string $grade = 'primaria_3', string $letter = 'A', int $capacity = 30): int
{
    return DB::table('sections')->insertGetId(['school_year_id' => $schoolYearId, 'grade' => $grade, 'letter' => $letter, 'capacity' => $capacity]);
}

function enrollmentStudent(string $familyNames = 'Pérez', string $givenNames = 'Ana', string $birthDate = '2018-03-10', string $status = 'active'): int
{
    return DB::table('students')->insertGetId([
        'given_names' => $givenNames,
        'family_names' => $familyNames,
        'sex' => 'female',
        'birth_date' => $birthDate,
        'nationality' => 'V',
        'identification_type' => 'national_id',
        'identification' => 'V'.random_int(30000000, 39999999),
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @param  array<string, mixed>  $columns
 */
function enrollmentRow(int $studentId, int $schoolYearId, int $sectionId, array $columns = []): int
{
    return DB::table('enrollments')->insertGetId([
        'student_id' => $studentId,
        'school_year_id' => $schoolYearId,
        'section_id' => $sectionId,
        'grade' => 'primaria_3',
        'starts_on' => '2026-09-16',
        'registered_at' => '2026-09-16 08:00',
        'registered_by' => 1,
        ...$columns,
    ]);
}
