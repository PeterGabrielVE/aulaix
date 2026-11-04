<?php

use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\StudentPendings\StudentPendingsQuery;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingStanding;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Revision;
use AulaX\Evaluation\Domain\Weight;
use Tests\Fixtures\Evaluation\EvaluationScene;

/**
 * SPEC-016 RVP-R05.2, R06.1: the pending subjects of a student, with the
 * grade and year where each was failed, how it stands and its final grade
 * after revision once decided.
 */
beforeEach(function () {
    $this->scene = new EvaluationScene;
    $this->scene->teaching->subjectNames = [4 => 'Matemática', 5 => 'Física', 6 => 'Química'];

    // Ana owes three subjects of 2.º año from school year 1; Luis, one.
    $this->scene->enrollments->owed = [
        new OwedPending(1, 4, 'media_2', 1),
        new OwedPending(1, 5, 'media_2', 1),
        new OwedPending(1, 6, 'media_2', 1),
        new OwedPending(2, 4, 'media_2', 1),
    ];
});

/**
 * The group of a subject of 2.º año in school year 2, Ana with the same
 * grade in its three terms.
 */
function groupWithGrade(int $subjectId, string $grade): int
{
    $store = test()->scene->store;
    $group = PendingGroup::open(2, $subjectId, 'media_2');
    $store->saveGroup($group);

    foreach ([1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'] as $term => $date) {
        $store->save(EvaluationPlan::reconstitute(null, 2, PlanOwner::pendingGroup((int) $group->id()), $subjectId, $term, [new Activity(null, 'Prueba', new DateTimeImmutable($date), Weight::of('100'))]));
        $activity = $store->planOfGroup((int) $group->id(), $term)->activities()[0];
        $store->saveGrades(ActivityGrades::of($activity->id, PlanOwner::pendingGroup((int) $group->id()), $activity->date, [1 => GradeMark::fromInput($grade)]));
    }

    return (int) $group->id();
}

function pendingsOfAna(): array
{
    $scene = test()->scene;
    $members = new PendingMembers($scene->store, $scene->store, $scene->store, $scene->store, $scene->enrollments, $scene->calendar);

    return array_map(
        fn ($view) => [$view->subject, $view->originGrade, $view->originSchoolYear, $view->standing, $view->score, $view->decidedIn],
        (new StudentPendingsQuery($members, $scene->enrollments, $scene->teaching, $scene->calendar))(1),
    );
}

it('shows each pending subject of the student with how it stands and its final grade after revision', function () {
    groupWithGrade(4, '15');
    $failed = groupWithGrade(5, '8');
    $this->scene->store->saveRevision(Revision::of(null, 2, 5, 1, PlanOwner::pendingGroup($failed), GradeMark::fromInput('NP')));
    $this->scene->calendar->terms = array_map(fn (array $term) => [...$term, 'status' => 'closed'], $this->scene->calendar->terms);

    expect(pendingsOfAna())->toBe([
        ['Física', '2.º año', '2025-2026', PendingStanding::FAILED, 8, '2026-2027'],
        ['Matemática', '2.º año', '2025-2026', PendingStanding::APPROVED, 15, '2026-2027'],
        ['Química', '2.º año', '2025-2026', PendingStanding::STUDYING, null, null],
    ]);
});

it('keeps a pending subject studied until the third term is closed and its revision is in', function () {
    groupWithGrade(4, '15');
    groupWithGrade(5, '8');

    expect(array_column(pendingsOfAna(), 3))->toBe([PendingStanding::STUDYING, PendingStanding::STUDYING, PendingStanding::STUDYING]);

    $this->scene->calendar->terms = array_map(fn (array $term) => [...$term, 'status' => 'closed'], $this->scene->calendar->terms);

    expect(array_column(pendingsOfAna(), 3))->toBe([PendingStanding::STUDYING, PendingStanding::APPROVED, PendingStanding::STUDYING]);
});

it('shows a subject approved in a year before, with that year', function () {
    $this->scene->calendar->activeYear = 3;
    groupWithGrade(4, '16');
    $this->scene->calendar->closedYears = [2];

    expect(pendingsOfAna()[1])->toBe(['Matemática', '2.º año', '2025-2026', PendingStanding::APPROVED, 16, '2026-2027']);
});
