<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-NF02, R11.1: the evaluation tables guard the scale, the
 * weights, one plan per sheet and one grade per student and activity,
 * whatever the code does.
 */
function evaluationMigration(): object
{
    return require File::glob(database_path('migrations/*_create_evaluation_tables.php'))[0];
}

beforeEach(function () {
    tenant();
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year, 'media_3');
    $this->subject = evaluationSubject();
    $this->student = enrollmentStudent();
});

it('keeps a grade between 1 and 20, or NP with no grade', function (array $columns, bool $accepted) {
    $activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->subject));
    $insert = fn () => DB::transaction(fn () => activityGradeRow($activity, $this->student, $columns));

    $accepted ? expect($insert())->toBeInt() : expect($insert)->toThrow(QueryException::class, 'activity_grades_score_or_not_presented');
})->with([
    '1' => [['score' => 100], true],
    '20' => [['score' => 2000], true],
    'NP' => [['score' => null, 'not_presented' => true], true],
    'below 1' => [['score' => 99], false],
    'above 20' => [['score' => 2001], false],
    'NP with a grade' => [['score' => 1500, 'not_presented' => true], false],
    'neither' => [['score' => null], false],
]);

it('keeps one grade per student and activity', function () {
    $activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->subject));
    activityGradeRow($activity, $this->student);

    expect(fn () => activityGradeRow($activity, $this->student))->toThrow(QueryException::class, 'unique');
});

it('refuses an activity with no weight and a plan out of the three terms', function () {
    $plan = evaluationPlanRow($this->year, $this->section, $this->subject);

    expect(fn () => DB::transaction(fn () => evaluationActivityRow($plan, ['weight' => 0])))->toThrow(QueryException::class, 'evaluation_activities_weight_positive')
        ->and(fn () => evaluationPlanRow($this->year, $this->section, $this->subject, 4))->toThrow(QueryException::class, 'evaluation_plans_term_known');
});

it('keeps one plan per section, subject and term', function () {
    evaluationPlanRow($this->year, $this->section, $this->subject);

    expect(fn () => evaluationPlanRow($this->year, $this->section, $this->subject))->toThrow(QueryException::class, 'evaluation_plans_one_per_sheet');
});

it('removes the activities and grades with their plan and keeps a student, a section or a subject with grades', function () {
    $activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->subject));
    activityGradeRow($activity, $this->student);

    expect(fn () => DB::transaction(fn () => DB::table('students')->where('id', $this->student)->delete()))->toThrow(QueryException::class, 'foreign key')
        ->and(fn () => DB::transaction(fn () => DB::table('sections')->where('id', $this->section)->delete()))->toThrow(QueryException::class, 'foreign key')
        ->and(fn () => DB::transaction(fn () => DB::table('subjects')->where('id', $this->subject)->delete()))->toThrow(QueryException::class, 'foreign key');

    DB::table('evaluation_plans')->delete();

    expect(DB::table('evaluation_activities')->count())->toBe(0)
        ->and(DB::table('activity_grades')->count())->toBe(0);
});

it('keeps one literal or report per student, year, period and kind', function (array $columns, string $constraint) {
    $mark = ['student_id' => $this->student, 'school_year_id' => $this->year, 'period' => '1', 'kind' => 'literal', 'literal' => 'A', 'incomplete' => false];

    expect(fn () => DB::transaction(fn () => DB::table('student_marks')->insert([...$mark, ...$columns])))->toThrow(QueryException::class, $constraint);
})->with([
    'literal F' => [['literal' => 'F'], 'student_marks_literal_known'],
    'period 4' => [['period' => '4'], 'student_marks_period_known'],
    'unknown kind' => [['kind' => 'grade'], 'student_marks_kind_known'],
    'report too long' => [['kind' => 'report', 'literal' => null, 'description' => str_repeat('a', 5001)], 'student_marks_description_length'],
]);

it('refuses a repeated literal of a student in a period', function () {
    $mark = ['student_id' => $this->student, 'school_year_id' => $this->year, 'period' => 'final', 'kind' => 'literal', 'literal' => 'B', 'incomplete' => false];
    DB::table('student_marks')->insert($mark);

    expect(fn () => DB::table('student_marks')->insert($mark))->toThrow(QueryException::class, 'unique');
});

it('rolls back and applies again', function () {
    evaluationMigration()->down();

    expect(Schema::hasTable('evaluation_plans'))->toBeFalse()
        ->and(Schema::hasTable('evaluation_activities'))->toBeFalse()
        ->and(Schema::hasTable('activity_grades'))->toBeFalse()
        ->and(Schema::hasTable('student_marks'))->toBeFalse();

    evaluationMigration()->up();

    expect(Schema::hasTable('evaluation_plans'))->toBeTrue()
        ->and(Schema::hasTable('evaluation_activities'))->toBeTrue()
        ->and(Schema::hasTable('activity_grades'))->toBeTrue()
        ->and(Schema::hasTable('student_marks'))->toBeTrue();
});
