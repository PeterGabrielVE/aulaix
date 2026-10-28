<?php

use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentCommand;
use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentHandler;
use AulaX\Enrollment\Application\ChangePlacement\ChangePlacementCommand;
use AulaX\Enrollment\Application\ChangePlacement\ChangeSectionHandler;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Roster\EnrolledCountsQuery;
use AulaX\Enrollment\Application\Roster\HistoryEntry;
use AulaX\Enrollment\Application\Roster\SectionNotFound;
use AulaX\Enrollment\Application\Roster\SectionRosterQuery;
use AulaX\Enrollment\Application\Roster\StudentHistoryQuery;
use AulaX\Enrollment\Application\Roster\YearOverviewQuery;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R02.3, R04.6, R09.1 – R09.3, R14.2, R14.6: who is enrolled
 * where, the students left to enroll, and the history of a student.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->scene->structure->sections[2][0] = new SectionFact(10, 2, 'primaria_3', '3.er grado', 'primaria', 'A', 1);
    $this->scene->students->add(5, '2018-03-10', name: 'Pérez, Ana');
    $this->scene->students->add(6, '2018-04-10', name: 'Álvarez, Luis');
    $this->scene->students->add(7, '2018-05-10', name: 'Mora, Eva');
    $this->scene->students->add(8, '2018-06-10', active: false, name: 'Rojas, Paz');
});

function enrollInRoster(int $student, int $section, ?string $overCapacity = null): int
{
    $scene = test()->scene;

    return (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, 2, $section, '2026-09-16', $overCapacity, null, 3),
    )->enrollmentId;
}

it('shows each section of the year with its capacity, its current enrollments and whether it is over capacity', function () {
    enrollInRoster(5, 10);
    enrollInRoster(6, 10, 'Hermanos');
    enrollInRoster(7, 11);
    $overview = (new YearOverviewQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions, $this->scene->calendar))(2);

    expect(array_map(fn ($section) => [$section->id, $section->letter, $section->capacity, $section->enrolled, $section->overCapacity], $overview->sections))->toBe([
        [10, 'A', 1, 2, true],
        [11, 'B', 30, 1, false],
        [20, 'A', 30, 0, false],
    ])->and($overview->sections[0]->gradeName)->toBe('3.er grado');
});

it('lists the active students without a current enrollment in the year', function () {
    $annulled = enrollInRoster(5, 10);
    (new AnnulEnrollmentHandler($this->scene->enrollments, $this->scene->calendar, $this->scene->graded(), $this->scene->permissions, $this->scene->clock, $this->scene->audit, $this->scene->transactions))(
        new AnnulEnrollmentCommand($annulled, 'duplicate', null, 3),
    );
    enrollInRoster(7, 11);
    $overview = (new YearOverviewQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions, $this->scene->calendar))(2);

    expect(array_map(fn ($student) => $student->id, $overview->unenrolled))->toBe([5, 6]);
});

it('lists the students of a section by name, with their identification and over capacity reason', function () {
    enrollInRoster(5, 10);
    enrollInRoster(6, 10, 'Hermanos');
    $roster = (new SectionRosterQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions))(2, 10);

    expect($roster->section->enrolled)->toBe(2)
        ->and(array_map(fn ($student) => [$student->fullName, $student->identification, $student->overCapacityReason], $roster->students))->toBe([
            ['Álvarez, Luis', 'V30000006', 'Hermanos'],
            ['Pérez, Ana', 'V30000005', null],
        ])
        ->and(fn () => (new SectionRosterQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions))(2, 99))->toThrow(SectionNotFound::class);
});

it('gives other modules the current enrollments per section', function () {
    enrollInRoster(5, 10);
    enrollInRoster(7, 11);

    expect((new EnrolledCountsQuery($this->scene->enrollments))(2))->toBe([10 => 1, 11 => 1]);
});

it('shows the history of a student: where, from, until and why it ended, annulments included', function () {
    $first = enrollInRoster(5, 10);
    $this->scene->clock->now = new DateTimeImmutable('2026-10-15 09:00');
    (new ChangeSectionHandler($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->calendar, $this->scene->ages, $this->scene->permissions, $this->scene->clock, $this->scene->audit, $this->scene->transactions))(
        new ChangePlacementCommand($first, 11, '2026-10-01', 'Reorganización', null, null, 3),
    );
    $history = (new StudentHistoryQuery($this->scene->enrollments, $this->scene->structure, $this->scene->permissions))(5);

    expect($history)->toEqual([
        new HistoryEntry($first, 2, '3.er grado', 'A', '2026-09-16', '2026-10-01', 'Cambio de sección', 'Reorganización', null, null, null, null, false),
        new HistoryEntry($first + 1, 2, '3.er grado', 'B', '2026-10-01', null, null, null, null, null, null, null, true),
    ]);

    (new AnnulEnrollmentHandler($this->scene->enrollments, $this->scene->calendar, $this->scene->graded(), $this->scene->permissions, $this->scene->clock, $this->scene->audit, $this->scene->transactions))(
        new AnnulEnrollmentCommand($first + 1, 'other', 'Sección equivocada', 3),
    );
    $annulled = (new StudentHistoryQuery($this->scene->enrollments, $this->scene->structure, $this->scene->permissions))(5)[1];

    expect([$annulled->endReason, $annulled->annulmentReason, $annulled->annulmentNote, $annulled->annulledBy, $annulled->annulledAt, $annulled->current])
        ->toBe(['Anulada', 'Otro', 'Sección equivocada', 3, '2026-10-15 09:00', false]);
});

it('shows enrollments only to whoever may see students', function () {
    $this->scene->permissions->view = false;

    expect(fn () => (new YearOverviewQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions, $this->scene->calendar))(2))->toThrow(AuthorizationDenied::class)
        ->and(fn () => (new SectionRosterQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions))(2, 10))->toThrow(AuthorizationDenied::class)
        ->and(fn () => (new StudentHistoryQuery($this->scene->enrollments, $this->scene->structure, $this->scene->permissions))(5))->toThrow(AuthorizationDenied::class);
});

it('opens the active year by default, else the newest one, with the years to choose from', function () {
    $this->scene->calendar->year(3, 2027, 'planned');
    $this->scene->structure->sections[3] = [];
    $query = new YearOverviewQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions, $this->scene->calendar);

    expect($query(null)->schoolYearId)->toBe(2)
        ->and($query(null)->status)->toBe('active')
        ->and(array_column($query(null)->years, 'id'))->toBe([3, 2])
        ->and($query(3)->status)->toBe('planned');

    $this->scene->calendar->years[2]['status'] = 'closed';

    expect($query(null)->schoolYearId)->toBe(3);
});
