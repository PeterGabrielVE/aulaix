<?php

use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Hooks\SectionScopedVisibility;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R08.1, R08.3, R08.4: which students a person sees, by the
 * sections it teaches in or guides and the grades it coordinates.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->scene->students->add(6, '2017-03-10');
    $scene = $this->scene;
    $enroll = fn (int $student, int $section) => (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, 2, $section, '2026-09-16', null, null, 3),
    );
    $enroll(5, 10);
    $enroll(6, 20);
    $this->scene->permissions->seesAll = false;
    $this->scene->permissions->accountId = 40;
});

function visibleFor(EnrollmentScene $scene): ?array
{
    return (new SectionScopedVisibility($scene->permissions, $scene->enrollments, $scene->structure, $scene->calendar))->visibleStudents();
}

it('a teacher sees the students of the sections it teaches in or guides', function () {
    $this->scene->structure->taught[40] = [10];

    expect(visibleFor($this->scene))->toBe([5]);
});

it('a coordinator sees the students of the grades it coordinates', function () {
    $this->scene->structure->coordinated[40] = ['primaria_4'];

    expect(visibleFor($this->scene))->toBe([6]);
});

it('without sections nor grades, or without an active year, it sees none', function () {
    expect(visibleFor($this->scene))->toBe([]);

    $this->scene->structure->taught[40] = [10];
    $this->scene->calendar->years[2]['status'] = 'closed';

    expect(visibleFor($this->scene))->toBe([]);
});

it('whoever manages sees everyone, and with no one signed in nothing is narrowed', function () {
    $this->scene->permissions->seesAll = true;

    expect(visibleFor($this->scene))->toBeNull();

    $this->scene->permissions->seesAll = false;
    $this->scene->permissions->accountId = null;

    expect(visibleFor($this->scene))->toBeNull();
});
