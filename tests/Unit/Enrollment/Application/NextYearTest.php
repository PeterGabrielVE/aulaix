<?php

use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\NextYear\EnrollNextYearCommand;
use AulaX\Enrollment\Application\NextYear\EnrollNextYearHandler;
use AulaX\Enrollment\Application\NextYear\NextYearProposal;
use AulaX\Enrollment\Application\NextYear\ProposeNextYearQuery;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\OverCapacity;
use AulaX\Enrollment\Domain\PromotionResult;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R07.1 – R07.7, R15.3, R12.1: enrolling the students of a
 * year in the next one, by their results, all or none.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->scene->structure->section(12, 2, 'primaria_6', 'A');
    $this->scene->structure->section(13, 2, 'media_5', 'A');
    $this->scene->calendar->year(3, 2027, 'planned');
    $this->scene->structure->section(30, 3, 'primaria_3', 'A');
    $this->scene->structure->section(31, 3, 'primaria_4', 'A', 1);
    $this->scene->structure->section(32, 3, 'primaria_4', 'B');
    // 3.er grado, 8 years old on December 2026: 9 in 2027, the regular age of 4.º grado.
    $this->scene->students->add(1, '2018-03-10', name: 'Pérez, Ana');
    $this->scene->students->add(2, '2018-04-10', name: 'Álvarez, Luis');
    $this->scene->students->add(3, '2018-05-10', name: 'Mora, Eva');
    $this->scene->students->add(4, '2017-05-10', name: 'Rojas, Paz');
    $this->scene->students->add(5, '2015-05-10', name: 'Díaz, Leo');
    $this->scene->students->add(6, '2010-05-10', name: 'Soto, Ema');
    $this->scene->students->add(7, '2010-06-10', name: 'Vera, Ivo');
    $this->scene->students->add(8, '2016-01-10', name: 'Gil, Tea');
    $this->ana = nextEnroll(1, 10, Outcome::Promoted);
    $this->luis = nextEnroll(2, 10, Outcome::Repeats);
    $this->eva = nextEnroll(3, 11, null);
    $this->paz = nextEnroll(4, 11, Outcome::Promoted);
    $this->leo = nextEnroll(5, 12, Outcome::Promoted);
    $this->ema = nextEnroll(6, 13, Outcome::Promoted);
    $this->ivo = nextEnroll(7, 13, Outcome::PromotedWithPending, [4]);
    $this->tea = nextEnroll(8, 11, Outcome::Promoted);
    $this->scene->clock->now = new DateTimeImmutable('2027-07-20 09:00');
    $this->scene->audit->recorded = [];
});

function nextEnroll(int $student, int $section, ?Outcome $outcome, array $pending = []): int
{
    $scene = test()->scene;
    $id = (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, 2, $section, '2026-09-16', null, 'Repitió', 3),
    )->enrollmentId;

    if ($outcome !== null) {
        $enrollment = $scene->enrollments->byId(EnrollmentId::fromInt($id));
        $enrollment->recordResult(PromotionResult::of($outcome, $pending), $pending, new DateTimeImmutable('2027-07-10'), 3);
        $scene->enrollments->save($enrollment);
    }

    return $id;
}

function propose(int $from = 2): NextYearProposal
{
    $scene = test()->scene;

    return (new ProposeNextYearQuery($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions))($from);
}

function enrollNextYear(array $placements, array $overCapacity = [], int $from = 2): int
{
    $scene = test()->scene;

    return (new EnrollNextYearHandler($scene->enrollments, $scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollNextYearCommand($from, $placements, $overCapacity, 9),
    );
}

it('proposes for each student the grade its result leads to, with the sections of that grade', function () {
    $proposal = propose();

    expect($proposal->toSchoolYearId)->toBe(3)
        ->and(array_map(fn ($entry) => [$entry->fullName, $entry->toGrade, $entry->sectionIds], $proposal->entries))->toBe([
            ['Álvarez, Luis', 'primaria_3', [30]],
            ['Pérez, Ana', 'primaria_4', [31, 32]],
            ['Rojas, Paz', 'primaria_4', [31, 32]],
        ]);
});

it('leaves out, saying why, who has no result, graduated, waits to graduate, has no grade to go to or is out of its age range', function () {
    $this->scene->students->add(3, '2018-05-10', active: true, name: 'Mora, Eva');
    $excluded = array_map(fn ($entry) => [$entry->fullName, $entry->reason], propose()->excluded);

    expect($excluded)->toEqualCanonicalizing([
        ['Mora, Eva', 'No tiene resultado de prosecución.'],
        ['Díaz, Leo', 'El plantel no tiene secciones de 1.er año el año siguiente.'],
        ['Soto, Ema', 'Egresó.'],
        ['Vera, Ivo', 'Está pendiente de egresar.'],
        ['Gil, Tea', 'Su edad está fuera del rango de 4.º grado: inscríbelo uno a uno con la excepción.'],
    ]);
});

it('leaves out who was withdrawn or is already enrolled in the next year', function () {
    $this->scene->students->add(4, '2017-05-10', active: false, name: 'Rojas, Paz');
    enrollNextYear([1 => 32]);
    $excluded = array_column(array_map(fn ($entry) => [$entry->fullName, $entry->reason], propose()->excluded), 1, 0);

    expect($excluded['Rojas, Paz'])->toBe('Está retirado.')
        ->and($excluded['Pérez, Ana'])->toBe('Ya está inscrito en el año siguiente.');
});

it('enrolls the chosen students all together, recorded once with how many and where', function () {
    $count = enrollNextYear([1 => 32, 2 => 30, 4 => 32]);

    expect($count)->toBe(3)
        ->and($this->scene->enrollments->currentOf(1, 3)?->placement()->sectionId)->toBe(32)
        ->and($this->scene->enrollments->currentOf(2, 3)?->placement()->grade)->toBe('primaria_3')
        ->and($this->scene->enrollments->currentOf(1, 3)?->registeredBy())->toBe(9)
        ->and($this->scene->enrollments->currentOf(1, 3)?->period()->startsOn->format('Y-m-d'))->toBe('2027-07-20')
        ->and($this->scene->calendar->lockedInsideTransaction)->toBeGreaterThan(0)
        ->and($this->scene->audit->types())->toBe(['enrollment.batch.enrolled'])
        ->and($this->scene->audit->changesOf(0)['students'])->toBe(['from' => null, 'to' => 3]);
});

it('over the capacity of a section, needs one reason for that section', function () {
    expect(fn () => enrollNextYear([1 => 31, 4 => 31]))->toThrow(OverCapacity::class)
        ->and($this->scene->enrollments->currentOf(1, 3))->toBeNull();

    enrollNextYear([1 => 31, 4 => 31], [31 => 'Hermanos en la sección']);

    expect($this->scene->enrollments->currentOf(4, 3)?->overCapacityReason())->toBe('Hermanos en la sección')
        ->and($this->scene->enrollments->currentOf(1, 3)?->overCapacityReason())->toBeNull();
});

it('if one student can not be enrolled, enrolls nobody', function () {
    expect(fn () => enrollNextYear([1 => 32, 3 => 30]))->toThrow(BusinessRuleViolation::class, 'Mora, Eva ya no está en la propuesta: vuelve a cargarla.')
        ->and(fn () => enrollNextYear([1 => 30]))->toThrow(BusinessRuleViolation::class, 'La sección elegida para Pérez, Ana no es de 4.º grado.')
        ->and($this->scene->enrollments->currentOf(1, 3))->toBeNull();
});

it('needs a next year that is not closed', function () {
    expect(fn () => propose(3))->toThrow(BusinessRuleViolation::class, 'No hay un año escolar siguiente.');

    $this->scene->calendar->years[3]['status'] = 'closed';

    expect(fn () => propose())->toThrow(BusinessRuleViolation::class, 'El año escolar siguiente está cerrado.');
});

it('refuses whoever may not manage enrollments', function () {
    $this->scene->permissions->manage = false;

    expect(fn () => propose())->toThrow(AuthorizationDenied::class)
        ->and(fn () => enrollNextYear([1 => 32]))->toThrow(AuthorizationDenied::class);
});
