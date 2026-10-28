<?php

use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\Literal;
use AulaX\Evaluation\Domain\MarkKind;
use AulaX\Evaluation\Domain\PlanChanged;
use AulaX\Evaluation\Domain\Score;
use AulaX\Evaluation\Domain\Weight;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentActivityGradesRepository;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentEvaluationPlanRepository;
use AulaX\Evaluation\Infrastructure\Persistence\EloquentStudentMarksRepository;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R11.1, NF02: plans and grades through the query builder, in
 * the open institution.
 */
beforeEach(function () {
    tenant();
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year, 'media_3');
    $this->subject = evaluationSubject();
    $this->plans = new EloquentEvaluationPlanRepository;
    $this->grades = new EloquentActivityGradesRepository;
});

function newActivity(string $name, string $date, string $weight, ?int $id = null): Activity
{
    return new Activity($id === null ? null : ActivityId::fromInt($id), $name, new DateTimeImmutable($date), Weight::of($weight));
}

function savedPlan(object $test, array $activities, int $term = 1): EvaluationPlan
{
    $plan = EvaluationPlan::reconstitute(null, $test->year, $test->section, $test->subject, $term, $activities);
    $test->plans->save($plan);

    return $test->plans->planOf($test->year, $test->section, $test->subject, $term);
}

it('saves a plan and reads its activities in order, with their ids', function () {
    $plan = savedPlan($this, [newActivity('Prueba', '2026-10-05', '60'), newActivity('Taller', '2026-10-12', '40')]);

    expect($plan->id())->toBeInt()
        ->and(array_map(fn (Activity $activity) => [$activity->name, $activity->date->format('Y-m-d'), $activity->weight->hundredths], $plan->activities()))
        ->toBe([['Prueba', '2026-10-05', 6000], ['Taller', '2026-10-12', 4000]])
        ->and($plan->activities()[0]->id)->not->toBeNull()
        ->and($this->plans->planOfActivity($plan->activities()[1]->id)?->id())->toBe($plan->id())
        ->and($this->plans->planOf($this->year, $this->section, $this->subject, 2))->toBeNull();
});

it('keeps the activities sent with their id, adds the new ones and removes the rest with their grades', function () {
    $plan = savedPlan($this, [newActivity('Prueba', '2026-10-05', '60'), newActivity('Taller', '2026-10-12', '40')]);
    [$test, $workshop] = $plan->activities();
    $student = enrollmentStudent();
    $this->grades->saveGrades(ActivityGrades::of($workshop->id, $this->section, $workshop->date, [$student => GradeMark::score(Score::of('15'))]));

    $this->plans->save(EvaluationPlan::reconstitute($plan->id(), $this->year, $this->section, $this->subject, 1, [
        newActivity('Examen', '2026-10-20', '50'),
        newActivity('Prueba corta', '2026-10-05', '50', $test->id->value),
    ]));
    $saved = $this->plans->planOf($this->year, $this->section, $this->subject, 1);

    expect(array_map(fn (Activity $activity) => $activity->name, $saved->activities()))->toBe(['Examen', 'Prueba corta'])
        ->and($saved->activities()[1]->id->value)->toBe($test->id->value)
        ->and(DB::table('activity_grades')->count())->toBe(0);
});

it('refuses a second first plan and the edit of a plan that changed meanwhile', function () {
    $plan = savedPlan($this, [newActivity('Prueba', '2026-10-05', '100')]);

    expect(fn () => $this->plans->save(EvaluationPlan::reconstitute(null, $this->year, $this->section, $this->subject, 1, [newActivity('Otra', '2026-10-05', '100')])))
        ->toThrow(PlanChanged::class);

    DB::table('evaluation_activities')->delete();

    expect(fn () => $this->plans->save(EvaluationPlan::reconstitute($plan->id(), $this->year, $this->section, $this->subject, 1, $plan->activities())))
        ->toThrow(PlanChanged::class);
});

it('reads the plans of a subject in a term, of every section', function () {
    savedPlan($this, [newActivity('Prueba', '2026-10-05', '100')]);
    $other = enrollmentSection($this->year, 'media_3', 'B');
    $this->plans->save(EvaluationPlan::reconstitute(null, $this->year, $other, $this->subject, 1, [newActivity('Examen', '2026-10-06', '100')]));
    savedPlan($this, [newActivity('Final', '2027-01-20', '100')], 2);

    expect(array_map(fn (EvaluationPlan $plan) => $plan->sectionId(), $this->plans->plansOfSubject($this->year, $this->subject, 1)))->toEqualCanonicalizing([$this->section, $other]);
});

it('saves the grades of an activity, with NP, and removes the ones cleared', function () {
    $activity = savedPlan($this, [newActivity('Prueba', '2026-10-05', '100')])->activities()[0];
    [$ana, $luis] = [enrollmentStudent(), enrollmentStudent('Álvarez', 'Luis')];
    $grades = $this->grades->forActivity($activity, $this->section);

    expect($grades->marks())->toBe([])
        ->and($this->grades->withGrades([$activity->id->value]))->toBe([]);

    $this->grades->saveGrades(ActivityGrades::of($activity->id, $this->section, $activity->date, [$ana => GradeMark::score(Score::of('9,5')), $luis => GradeMark::notPresented()]));
    $read = $this->grades->forActivity($activity, $this->section);

    expect($read->markOf($ana)?->value())->toBe(950)
        ->and($read->markOf($luis)?->isNotPresented())->toBeTrue()
        ->and($this->grades->withGrades([$activity->id->value, 999]))->toBe([$activity->id->value]);

    $this->grades->saveGrades(ActivityGrades::of($activity->id, $this->section, $activity->date, [$ana => GradeMark::score(Score::of('12'))]));
    $read = $this->grades->forActivity($activity, $this->section);

    expect($read->markOf($ana)?->value())->toBe(1200)
        ->and($read->markOf($luis))->toBeNull();
});

it('saves the literals and reports of a student, incomplete ones included, and reads them by student', function () {
    $repository = new EloquentStudentMarksRepository;
    [$ana, $luis] = [enrollmentStudent(), enrollmentStudent('Álvarez', 'Luis')];
    $marks = $repository->forStudents($this->year, [$ana, $luis]);

    expect($marks[$ana]->marks())->toBe([]);

    $marks[$ana]->recordLiteral(1, Literal::B, 'Lee con fluidez.');
    $marks[$ana]->fillIncomplete('2', MarkKind::Literal);
    $repository->saveMarks($marks[$ana]);
    $marks[$ana]->recordLiteral(1, Literal::A, null);
    $repository->saveMarks($marks[$ana]);
    $read = $repository->forStudents($this->year, [$ana, $luis]);

    expect($read[$ana]->markOf('1', MarkKind::Literal)?->literal)->toBe(Literal::A)
        ->and($read[$ana]->markOf('1', MarkKind::Literal)?->description)->toBeNull()
        ->and($read[$ana]->markOf('2', MarkKind::Literal)?->incomplete)->toBeTrue()
        ->and($read[$luis]->marks())->toBe([])
        ->and(DB::table('student_marks')->count())->toBe(2);
});
