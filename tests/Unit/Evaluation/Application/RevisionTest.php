<?php

use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Revision\SaveRevisionCommand;
use AulaX\Evaluation\Application\Revision\SaveRevisionHandler;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\GradeNotValid;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Evaluation\Domain\RevisionNotAllowed;
use AulaX\Evaluation\Domain\Weight;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-016 RVP-R01.1 – R01.8, R07.1, NF01: whoever teaches the subject in
 * the section records the revisions of the failed final grades, with the
 * third term closed and before the result of the year.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->teaching->teaching = [[9, 10, 4]];
    $this->scene->enrollments->enroll(1, 10, name: 'Pérez, Ana');
    $this->scene->enrollments->enroll(2, 10, name: 'Álvarez, Luis');
    // Left for another section: not in it any more.
    $this->scene->enrollments->enroll(3, 10, until: '2026-10-10', name: 'Mora, Eva');

    // Ana fails Matemática with 8; Luis passes it with 15.
    foreach ([1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'] as $term => $date) {
        $plan = EvaluationPlan::reconstitute(null, 2, 10, 4, $term, [new Activity(null, 'Prueba', new DateTimeImmutable($date), Weight::of('100'))]);
        $this->scene->store->save($plan);
        $activity = $this->scene->store->planOf(2, 10, 4, $term)->activities()[0];
        $this->scene->store->saveGrades(ActivityGrades::of($activity->id, 10, $activity->date, [1 => GradeMark::fromInput('8'), 2 => GradeMark::fromInput('15')]));
    }

    $this->scene->calendar->terms = array_map(fn (array $term) => [...$term, 'status' => 'closed'], $this->scene->calendar->terms);
    $this->scene->clock->now = new DateTimeImmutable('2027-07-01 10:00');
});

function saveRevisions(array $revisions, int $section = 10, int $subject = 4): void
{
    $scene = test()->scene;
    (new SaveRevisionHandler($scene->store, $scene->store, $scene->store, $scene->teaching, $scene->calendar, $scene->enrollments, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new SaveRevisionCommand(2, $section, $subject, $revisions),
    );
}

it('the teacher of the subject records the revision of a failed final, recorded with what changed', function () {
    saveRevisions([1 => '12']);

    expect($this->scene->store->revisions['2:4:1']->mark()?->value())->toBe(1200)
        ->and($this->scene->audit->types())->toBe(['evaluation.revisions.saved'])
        ->and($this->scene->audit->changesOf(0))->toBe(['subject' => ['from' => null, 'to' => 4], 'student_1' => ['from' => null, 'to' => '12']])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();

    saveRevisions([1 => '12,00']);

    expect($this->scene->audit->types())->toHaveCount(1);
});

it('refuses a revision out of the scale, of a final that passed, or of whoever left the section', function () {
    expect(fn () => saveRevisions([1 => '21']))->toThrow(GradeNotValid::class, 'La nota debe estar entre 1 y 20, con hasta dos decimales.')
        ->and(fn () => saveRevisions([2 => '12']))->toThrow(RevisionNotAllowed::class, 'La definitiva no está reprobada: no lleva revisión.')
        ->and(fn () => saveRevisions([3 => '12']))->toThrow(GradeNotValid::class, 'El estudiante no tiene inscripción vigente en la sección.')
        ->and($this->scene->store->revisions)->toBe([]);
});

it('takes NP as a revision', function () {
    saveRevisions([1 => 'NP']);

    expect($this->scene->store->revisions['2:4:1']->mark()?->isNotPresented())->toBeTrue();
});

it('refuses revisions before the third term is closed, in a closed year, and once the result is recorded', function () {
    $this->scene->enrollments->results = [1];

    expect(fn () => saveRevisions([1 => '12']))->toThrow(RevisionNotAllowed::class, 'El estudiante ya tiene su resultado del año: corrígelo primero en Prosecución.');

    $this->scene->enrollments->results = [];
    $this->scene->calendar->yearClosed = true;

    expect(fn () => saveRevisions([1 => '12']))->toThrow(NotEditable::class, 'El año escolar está cerrado: sus notas no cambian.');

    $this->scene->calendar->yearClosed = false;
    $this->scene->calendar->terms[3]['status'] = 'open';

    expect(fn () => saveRevisions([1 => '12']))->toThrow(RevisionNotAllowed::class, 'La revisión se registra con el tercer lapso cerrado.');
});

it('only whoever teaches the subject in the section records its revisions', function () {
    $this->scene->teaching->teaching = [];

    expect(fn () => saveRevisions([1 => '12']))->toThrow(AuthorizationDenied::class, 'No dictas esa materia en esa sección.')
        ->and(fn () => saveRevisions([1 => '12'], subject: 9))->toThrow(EvaluationNotFound::class)
        ->and(fn () => saveRevisions([1 => '12'], section: 20))->toThrow(EvaluationNotFound::class);
});
