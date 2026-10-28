<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R01.2, NF02, NF03: the enrollment tables guard their own
 * rules, whatever the code does.
 */
beforeEach(function () {
    tenant();
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year);
    $this->student = enrollmentStudent();
});

it('keeps one current enrollment per student and year', function () {
    enrollmentRow($this->student, $this->year, $this->section);

    expect(fn () => DB::transaction(fn () => enrollmentRow($this->student, $this->year, $this->section)))->toThrow(QueryException::class, 'enrollments_one_current_per_year');

    DB::table('enrollments')->update(['ends_on' => '2026-10-01', 'end_reason' => 'section_change']);
    enrollmentRow($this->student, $this->year, $this->section, ['starts_on' => '2026-10-01']);

    $next = enrollmentYear(2027, 'planned');
    enrollmentRow($this->student, $next, enrollmentSection($next), ['starts_on' => '2027-09-16']);

    expect(DB::table('enrollments')->whereNull('ends_on')->count())->toBe(2);
});

it('has a reason to end if and only if it ended, and never ends before it starts', function (array $columns, string $constraint) {
    expect(fn () => enrollmentRow($this->student, $this->year, $this->section, $columns))->toThrow(QueryException::class, $constraint);
})->with([
    'ended without reason' => [['ends_on' => '2026-10-01'], 'enrollments_end_has_reason'],
    'reason without end' => [['end_reason' => 'withdrawal'], 'enrollments_end_has_reason'],
    'ends before it starts' => [['ends_on' => '2026-09-01', 'end_reason' => 'withdrawal'], 'enrollments_ends_after_start'],
    'unknown reason' => [['ends_on' => '2026-10-01', 'end_reason' => 'expelled'], 'enrollments_end_reason_known'],
    'annulled without who' => [['ends_on' => '2026-09-16', 'end_reason' => 'annulled'], 'enrollments_annulment_is_complete'],
    'annulment of a current one' => [['annulment_reason' => 'duplicate', 'annulled_by' => 1, 'annulled_at' => '2026-09-16 09:00'], 'enrollments_annulment_is_complete'],
    'exception without who' => [['age_exception_reason' => 'Adelantado'], 'enrollments_age_exception_is_complete'],
    'unknown grade' => [['grade' => 'sexto'], 'enrollments_grade_known'],
]);

it('keeps a section, a school year and a student while they have enrollments', function () {
    enrollmentRow($this->student, $this->year, $this->section);

    expect(fn () => DB::transaction(fn () => DB::table('sections')->where('id', $this->section)->delete()))->toThrow(QueryException::class, 'foreign key')
        ->and(fn () => DB::transaction(fn () => DB::table('school_years')->where('id', $this->year)->delete()))->toThrow(QueryException::class, 'foreign key');
});

it('keeps the pending subjects of an enrollment, each once', function () {
    $enrollment = enrollmentRow($this->student, $this->year, $this->section);
    $subject = DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']);
    DB::table('enrollment_pending_subjects')->insert(['enrollment_id' => $enrollment, 'subject_id' => $subject]);

    expect(fn () => DB::table('enrollment_pending_subjects')->insert(['enrollment_id' => $enrollment, 'subject_id' => $subject]))->toThrow(QueryException::class, 'unique');
});
