<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-NF02, R08.1 (ADR 0013): a plan belongs to a section or to a
 * group of pending subjects, exactly one; a group is one per subject and
 * grade of origin; a revision is a grade or NP, one per student.
 */
function revisionMigration(): object
{
    return require File::glob(database_path('migrations/*_create_revision_and_pending_tables.php'))[0];
}

function pendingGroupRow(int $schoolYearId, int $subjectId, string $originGrade = 'media_3'): int
{
    return DB::table('pending_groups')->insertGetId(['school_year_id' => $schoolYearId, 'subject_id' => $subjectId, 'origin_grade' => $originGrade]);
}

beforeEach(function () {
    tenant();
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year, 'media_4');
    $this->subject = evaluationSubject();
    $this->student = enrollmentStudent();
    $this->group = pendingGroupRow($this->year, $this->subject);
});

it('keeps a plan of a section or of a group, never both nor neither', function () {
    $plan = ['school_year_id' => $this->year, 'subject_id' => $this->subject, 'term' => 1];

    expect(DB::table('evaluation_plans')->insertGetId([...$plan, 'pending_group_id' => $this->group]))->toBeInt()
        ->and(fn () => DB::transaction(fn () => DB::table('evaluation_plans')->insert([...$plan, 'term' => 2, 'section_id' => $this->section, 'pending_group_id' => $this->group])))
        ->toThrow(QueryException::class, 'evaluation_plans_one_owner')
        ->and(fn () => DB::transaction(fn () => DB::table('evaluation_plans')->insert([...$plan, 'term' => 2])))
        ->toThrow(QueryException::class, 'evaluation_plans_one_owner');
});

it('keeps one plan per section and term, and one per group and term', function () {
    evaluationPlanRow($this->year, $this->section, $this->subject);
    DB::table('evaluation_plans')->insert(['school_year_id' => $this->year, 'subject_id' => $this->subject, 'term' => 1, 'pending_group_id' => $this->group]);

    expect(fn () => DB::transaction(fn () => evaluationPlanRow($this->year, $this->section, $this->subject)))->toThrow(QueryException::class, 'evaluation_plans_one_per_sheet')
        ->and(fn () => DB::table('evaluation_plans')->insert(['school_year_id' => $this->year, 'subject_id' => $this->subject, 'term' => 1, 'pending_group_id' => $this->group]))
        ->toThrow(QueryException::class, 'evaluation_plans_one_per_group_term');
});

it('keeps one group per subject and grade of origin, of Media General', function () {
    expect(fn () => DB::transaction(fn () => pendingGroupRow($this->year, $this->subject)))->toThrow(QueryException::class, 'unique')
        ->and(fn () => pendingGroupRow($this->year, $this->subject, 'primaria_3'))->toThrow(QueryException::class, 'pending_groups_origin_grade_known');
});

it('removes the plans and grades of a group with it', function () {
    $plan = DB::table('evaluation_plans')->insertGetId(['school_year_id' => $this->year, 'subject_id' => $this->subject, 'term' => 1, 'pending_group_id' => $this->group]);
    activityGradeRow(evaluationActivityRow($plan), $this->student);

    DB::table('pending_groups')->where('id', $this->group)->delete();

    expect(DB::table('evaluation_plans')->count())->toBe(0)
        ->and(DB::table('activity_grades')->count())->toBe(0);
});

it('keeps a revision as a grade between 1 and 20 or NP, of a section or a group, once per student', function () {
    $revision = ['school_year_id' => $this->year, 'subject_id' => $this->subject, 'student_id' => $this->student, 'section_id' => $this->section, 'score' => 1200, 'not_presented' => false];
    DB::table('grade_revisions')->insert($revision);

    expect(fn () => DB::transaction(fn () => DB::table('grade_revisions')->insert($revision)))->toThrow(QueryException::class, 'grade_revisions_one_per_section_subject')
        ->and(fn () => DB::transaction(fn () => DB::table('grade_revisions')->insert([...$revision, 'section_id' => null, 'pending_group_id' => $this->group, 'score' => 2001])))
        ->toThrow(QueryException::class, 'grade_revisions_score_or_not_presented')
        ->and(fn () => DB::transaction(fn () => DB::table('grade_revisions')->insert([...$revision, 'section_id' => null, 'score' => null, 'not_presented' => true])))
        ->toThrow(QueryException::class, 'grade_revisions_one_owner');

    DB::table('grade_revisions')->insert([...$revision, 'section_id' => null, 'pending_group_id' => $this->group, 'score' => null, 'not_presented' => true]);

    expect(fn () => DB::table('grade_revisions')->insert([...$revision, 'section_id' => null, 'pending_group_id' => $this->group]))
        ->toThrow(QueryException::class, 'grade_revisions_one_per_group_student');
});

it('rolls back, removing the plans of groups, and applies again', function () {
    DB::table('evaluation_plans')->insert(['school_year_id' => $this->year, 'subject_id' => $this->subject, 'term' => 1, 'pending_group_id' => $this->group]);
    evaluationPlanRow($this->year, $this->section, $this->subject);

    revisionMigration()->down();

    expect(Schema::hasTable('pending_groups'))->toBeFalse()
        ->and(Schema::hasTable('grade_revisions'))->toBeFalse()
        ->and(Schema::hasColumn('evaluation_plans', 'pending_group_id'))->toBeFalse()
        ->and(DB::table('evaluation_plans')->count())->toBe(1);

    revisionMigration()->up();

    expect(Schema::hasTable('pending_groups'))->toBeTrue()
        ->and(Schema::hasColumn('evaluation_plans', 'pending_group_id'))->toBeTrue();
});
