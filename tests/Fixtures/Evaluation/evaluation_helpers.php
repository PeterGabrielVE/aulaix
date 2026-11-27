<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__).'/Enrollment/enrollment_helpers.php';

/**
 * Rows for the evaluation tests against PostgreSQL, in the active
 * institution (Row Level Security fills institution_id).
 */
function evaluationSubject(string $name = 'Matemática'): int
{
    return DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => $name, 'education_level' => 'media_general']);
}

function evaluationPlanRow(int $schoolYearId, int $sectionId, int $subjectId, int $term = 1): int
{
    return DB::table('evaluation_plans')->insertGetId([
        'school_year_id' => $schoolYearId,
        'section_id' => $sectionId,
        'subject_id' => $subjectId,
        'term' => $term,
    ]);
}

/**
 * @param  array<string, mixed>  $columns
 */
function evaluationActivityRow(int $planId, array $columns = []): int
{
    return DB::table('evaluation_activities')->insertGetId([
        'plan_id' => $planId,
        'name' => 'Prueba',
        'date' => '2026-10-05',
        'weight' => 10000,
        'position' => 0,
        ...$columns,
    ]);
}

/**
 * @param  array<string, mixed>  $columns
 */
function activityGradeRow(int $activityId, int $studentId, array $columns = []): int
{
    return DB::table('activity_grades')->insertGetId([
        'activity_id' => $activityId,
        'student_id' => $studentId,
        'score' => 1500,
        'not_presented' => false,
        ...$columns,
    ]);
}

/**
 * A Media General institution with school year 2026-2027 active and its
 * first term open: 3.er año A with Matemática and Física in its curriculum,
 * Ana Rivas teaching Matemática, and Pérez, Ana and Álvarez, Luis enrolled.
 *
 * @return array{institution: InstitutionModel, year: int, section: int, math: int, physics: int, teacher: AccountModel, ana: int, luis: int}
 */
function evaluationSetting(): array
{
    $institution = tenant(['education_levels' => ['media_general']]);
    $year = evaluationYear();
    $section = enrollmentSection($year, 'media_3');
    $math = evaluationSubject('Matemática');
    $physics = evaluationSubject('Física');
    DB::table('curriculum_subjects')->insert([
        ['school_year_id' => $year, 'grade' => 'media_3', 'subject_id' => $math],
        ['school_year_id' => $year, 'grade' => 'media_3', 'subject_id' => $physics],
    ]);
    $teacher = userWithRole($institution, 'Docente', ['name' => 'Ana Rivas']);
    DB::table('teaching_assignments')->insert(['section_id' => $section, 'subject_id' => $math, 'account_id' => $teacher->id]);
    $ana = enrollmentStudent('Pérez', 'Ana', '2011-03-10');
    $luis = enrollmentStudent('Álvarez', 'Luis', '2011-05-02');

    foreach ([$ana, $luis] as $student) {
        enrollmentRow($student, $year, $section, ['grade' => 'media_3']);
    }

    return compact('institution', 'year', 'section', 'math', 'physics', 'teacher', 'ana', 'luis');
}

/**
 * School year 2026-2027, active, with its first term open.
 */
function evaluationYear(): int
{
    $year = enrollmentYear();

    foreach ([1 => ['2026-09-16', '2026-12-15', 'open'], 2 => ['2027-01-07', '2027-03-31', 'pending'], 3 => ['2027-04-12', '2027-07-15', 'pending']] as $number => [$start, $end, $status]) {
        DB::table('terms')->insert(['school_year_id' => $year, 'number' => $number, 'starts_on' => $start, 'ends_on' => $end, 'status' => $status]);
    }

    return $year;
}

/**
 * An institution of Inicial and Primaria, its first term open: Ana Rivas
 * guides 1.er grado A (Pérez, Ana) and 2.º nivel A of Preescolar (Mora, Eva).
 *
 * @return array{institution: InstitutionModel, year: int, primary: int, preschool: int, guide: AccountModel, ana: int, eva: int}
 */
function marksSetting(): array
{
    $institution = tenant(['education_levels' => ['inicial', 'primaria']]);
    $year = evaluationYear();
    $guide = userWithRole($institution, 'Docente', ['name' => 'Ana Rivas']);
    $primary = enrollmentSection($year, 'primaria_1');
    $preschool = enrollmentSection($year, 'preescolar_2', capacity: 25);
    DB::table('sections')->whereIn('id', [$primary, $preschool])->update(['guide_account_id' => $guide->id]);
    $ana = enrollmentStudent('Pérez', 'Ana', '2020-03-10');
    $eva = enrollmentStudent('Mora', 'Eva', '2022-05-02');
    enrollmentRow($ana, $year, $primary, ['grade' => 'primaria_1']);
    enrollmentRow($eva, $year, $preschool, ['grade' => 'preescolar_2']);

    return compact('institution', 'year', 'primary', 'preschool', 'guide', 'ana', 'eva');
}

/**
 * @param  array<string, mixed>  $columns
 */
function revisionRow(int $schoolYearId, int $sectionId, int $subjectId, int $studentId, array $columns = []): int
{
    return DB::table('grade_revisions')->insertGetId([
        'school_year_id' => $schoolYearId,
        'section_id' => $sectionId,
        'subject_id' => $subjectId,
        'student_id' => $studentId,
        'score' => null,
        'not_presented' => true,
        ...$columns,
    ]);
}
