<?php

use AulaX\Evaluation\Application\PendingGroups\PendingGroupSheetQuery;
use AulaX\Evaluation\Application\PendingGroups\PendingGroupSheetView;
use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PendingStanding;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Revision;
use AulaX\Evaluation\Domain\Weight;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-016 RVP-R04.3, R04.6, R05.1, R05.2: the sheet of a group of pending
 * subjects, with the grades of each term, the final grade, the revision and
 * how each member stands; for its teacher, a coordinator of the grade of
 * origin or a manager, and for nobody else.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->enrollments->owed = [new OwedPending(1, 4, 'media_2', 7), new OwedPending(2, 4, 'media_2', 7)];
    $this->scene->enrollments->students = [1 => new RosterStudentFact(1, 'Pérez, Ana', 'V1'), 2 => new RosterStudentFact(2, 'Álvarez, Luis', 'V2')];
    $group = PendingGroup::open(2, 4, 'media_2');
    $group->assignTeacher(9);
    $this->scene->store->saveGroup($group);
    $this->group = (int) $group->id();

    // Ana fails it with 8, Luis passes it with 15; the three terms closed.
    foreach ([1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'] as $term => $date) {
        $this->scene->store->save(EvaluationPlan::reconstitute(null, 2, PlanOwner::pendingGroup($this->group), 4, $term, [new Activity(null, 'Prueba', new DateTimeImmutable($date), Weight::of('100'))]));
        $activity = $this->scene->store->planOfGroup($this->group, $term)->activities()[0];
        $this->scene->store->saveGrades(ActivityGrades::of($activity->id, PlanOwner::pendingGroup($this->group), $activity->date, [1 => GradeMark::fromInput('8'), 2 => GradeMark::fromInput('15')]));
    }

    $this->scene->calendar->terms = array_map(fn (array $term) => [...$term, 'status' => 'closed'], $this->scene->calendar->terms);
});

function groupSheet(int $term = 3, ?int $group = null): PendingGroupSheetView
{
    $scene = test()->scene;
    $members = new PendingMembers($scene->store, $scene->store, $scene->store, $scene->store, $scene->enrollments, $scene->calendar);

    return (new PendingGroupSheetQuery($members, $scene->store, $scene->teaching, $scene->calendar, $scene->permissions))($group ?? test()->group, $term);
}

it('shows the members with their grades, final grade, revision and how they stand', function () {
    $sheet = groupSheet();

    expect($sheet->subject)->toBe('Matemática')
        ->and($sheet->originGrade)->toBe('media_2')
        ->and($sheet->canGrade)->toBeTrue()
        ->and($sheet->revisionsOpen)->toBeTrue()
        ->and(array_map(fn ($row) => [$row->fullName, $row->termScore, $row->finalScore, $row->standing], $sheet->rows))->toBe([
            ['Álvarez, Luis', 15, 15, PendingStanding::APPROVED],
            ['Pérez, Ana', 8, 8, PendingStanding::STUDYING],
        ]);

    $this->scene->store->saveRevision(Revision::of(null, 2, 4, 1, PlanOwner::pendingGroup($this->group), GradeMark::fromInput('7')));

    expect(groupSheet()->rows[1]->standing)->toBe(PendingStanding::FAILED)
        ->and(groupSheet()->rows[1]->revision)->toBe('7');
});

it('shows the sheet to a coordinator of the grade of origin and to a manager, and to nobody else', function () {
    $this->scene->permissions->accountId = 8;

    expect(fn () => groupSheet())->toThrow(PendingGroupNotFound::class);

    $this->scene->teaching->coordinated[8] = ['media_2'];

    expect(groupSheet()->canGrade)->toBeFalse()
        ->and(groupSheet()->revisionsOpen)->toBeFalse();

    $this->scene->teaching->coordinated[8] = [];
    $this->scene->permissions->seesAll = true;

    expect(groupSheet()->rows)->toHaveCount(2)
        ->and(fn () => groupSheet(group: 99))->toThrow(PendingGroupNotFound::class)
        ->and(fn () => groupSheet(term: 4))->toThrow(PendingGroupNotFound::class);
});
