<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Gradebook\GradebookQuery;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

pest()->group('rls');

/**
 * SPEC-011 EVA-R11.1 – R11.3, NF02: an institution sees and changes only its
 * own plans and grades; the ids of another one do not exist for it.
 */
beforeEach(function () {
    $this->norte = tenant();
    $this->actingAs(userWithRole($this->norte, 'Administrador'));
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year, 'media_3');
    $this->subject = evaluationSubject();
    $this->student = enrollmentStudent();
    $this->activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->subject));
    activityGradeRow($this->activity, $this->student);
    DB::table('student_marks')->insert(['student_id' => $this->student, 'school_year_id' => $this->year, 'period' => '1', 'kind' => 'literal', 'literal' => 'A']);
});

it('another institution sees none of its plans, activities, grades or literals', function () {
    $this->actingAs(userWithRole(tenant(), 'Administrador'));

    expect(DB::table('evaluation_plans')->count())->toBe(0)
        ->and(DB::table('evaluation_activities')->count())->toBe(0)
        ->and(DB::table('activity_grades')->count())->toBe(0)
        ->and(DB::table('student_marks')->count())->toBe(0)
        ->and(app(EvaluationPlanRepository::class)->planOfActivity(ActivityId::fromInt($this->activity)))->toBeNull();
});

it('another institution can not open its sheet nor grade its activity', function () {
    $this->actingAs(userWithRole(tenant(), 'Administrador'));

    expect(fn () => app(GradebookQuery::class)($this->year, $this->section, $this->subject, 1))->toThrow(EvaluationNotFound::class)
        ->and(fn () => app(SaveActivityGradesHandler::class)($this->activity, [$this->student => '20']))->toThrow(EvaluationNotFound::class);
});
