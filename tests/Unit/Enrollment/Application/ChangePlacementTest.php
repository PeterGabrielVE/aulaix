<?php

use AulaX\Enrollment\Application\ChangePlacement\ChangeGradeHandler;
use AulaX\Enrollment\Application\ChangePlacement\ChangePlacementCommand;
use AulaX\Enrollment\Application\ChangePlacement\ChangeSectionHandler;
use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Domain\AgeOutOfRange;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\OverCapacity;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R04.1 – R04.5, R04.7 – R04.9, R12.1: moving a student to
 * another section of its grade, or, by a directive, of another grade.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->enrollmentId = enrolledInScene();
});

function enrolledInScene(int $student = 5, int $section = 10): int
{
    $scene = test()->scene;

    return (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, 2, $section, '2026-09-16', null, null, 3),
    )->enrollmentId;
}

function changeCommand(int $section, ?string $date = '2026-10-01', ?string $reason = 'Reorganización', ?string $overCapacity = null, ?string $ageException = null, ?int $enrollment = null): ChangePlacementCommand
{
    return new ChangePlacementCommand($enrollment ?? test()->enrollmentId, $section, $date, $reason, $overCapacity, $ageException, 9);
}

function changeSection(ChangePlacementCommand $command): int
{
    $scene = test()->scene;

    return (new ChangeSectionHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))($command);
}

function changeGrade(ChangePlacementCommand $command): int
{
    $scene = test()->scene;

    return (new ChangeGradeHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))($command);
}

it('moves a student to another section of its grade, ending the enrollment and opening the next one on that date', function () {
    $this->scene->audit->recorded = [];
    $next = changeSection(changeCommand(11));
    $previous = $this->scene->enrollments->byId(EnrollmentId::fromInt($this->enrollmentId));
    $current = $this->scene->enrollments->currentOf(5, 2);

    expect($current?->id()?->value)->toBe($next)
        ->and($current?->placement()->sectionId)->toBe(11)
        ->and($current?->period()->startsOn->format('Y-m-d'))->toBe('2026-10-01')
        ->and($current?->previousId())->toBe($this->enrollmentId)
        ->and($previous?->period()->endReason)->toBe(EndReason::SectionChange)
        ->and($previous?->endNote())->toBe('Reorganización')
        ->and($this->scene->calendar->lockedInsideTransaction)->toBe(2)
        ->and($this->scene->audit->types())->toBe(['enrollment.enrollment.section_changed'])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('refuses a change without a reason, out of its dates, to another year or in a closed year', function () {
    $this->scene->calendar->year(3, 2027, 'planned');
    $this->scene->structure->section(30, 3, 'primaria_3');

    expect(fn () => changeSection(changeCommand(11, reason: '')))->toThrow(BusinessRuleViolation::class, 'Indica el motivo del cambio.')
        ->and(fn () => changeSection(changeCommand(11, date: '2026-09-01')))->toThrow(BusinessRuleViolation::class, 'La fecha no puede ser anterior al inicio de la inscripción (16/09/2026).')
        ->and(fn () => changeSection(changeCommand(11, date: '2026-10-20')))->toThrow(BusinessRuleViolation::class, 'La fecha no puede ser posterior a hoy.')
        ->and(fn () => changeSection(changeCommand(30)))->toThrow(BusinessRuleViolation::class, 'La sección no es de ese año escolar.');

    $this->scene->calendar->years[2]['status'] = 'closed';

    expect(fn () => changeSection(changeCommand(11)))->toThrow(BusinessRuleViolation::class, 'El año escolar está cerrado.')
        ->and($this->scene->enrollments->currentOf(5, 2)?->placement()->sectionId)->toBe(10);
});

it('applies the capacity to the section it moves to', function () {
    $this->scene->structure->sections[2][1] = new SectionFact(11, 2, 'primaria_3', '3.er grado', 'primaria', 'B', 1);
    $this->scene->students->add(6, '2018-01-01');
    enrolledInScene(6, 11);

    expect(fn () => changeSection(changeCommand(11)))->toThrow(OverCapacity::class);

    changeSection(changeCommand(11, overCapacity: 'Hermanos'));

    expect($this->scene->enrollments->currentOf(5, 2)?->overCapacityReason())->toBe('Hermanos');
});

it('a change of section does not change the grade', function () {
    expect(fn () => changeSection(changeCommand(20)))->toThrow(BusinessRuleViolation::class, 'El cambio de grado lo aprueba un directivo.');
});

it('a directive changes the grade, recorded with who approved it', function () {
    $this->scene->audit->recorded = [];
    // Born in 2017: 9 years old, the regular age of 4.º grado.
    $this->scene->students->add(5, '2017-03-10');
    changeGrade(changeCommand(20, reason: 'Ubicación por evaluación'));
    $current = $this->scene->enrollments->currentOf(5, 2);

    expect($current?->placement()->grade)->toBe('primaria_4')
        ->and($current?->registeredBy())->toBe(9)
        ->and($this->scene->enrollments->byId(EnrollmentId::fromInt($this->enrollmentId))?->period()->endReason)->toBe(EndReason::GradeChange)
        ->and($this->scene->audit->types())->toBe(['enrollment.enrollment.grade_changed'])
        ->and($this->scene->audit->changesOf(0)['approved_by'])->toBe(['from' => null, 'to' => 9]);
});

it('only a directive changes the grade', function () {
    $this->scene->permissions->approve = false;

    expect(fn () => changeGrade(changeCommand(20)))->toThrow(AuthorizationDenied::class, 'Solo un directivo puede aprobar esta excepción.');
});

it('a change of grade goes to another grade, and checks the age there', function () {
    expect(fn () => changeGrade(changeCommand(11)))->toThrow(BusinessRuleViolation::class, 'La sección es del mismo grado: usa el cambio de sección.');

    $this->scene->structure->section(40, 2, 'primaria_6', 'A');

    expect(fn () => changeGrade(changeCommand(40)))->toThrow(AgeOutOfRange::class, 'Tiene 8 años y el rango del grado es de 10 a 12 años: hace falta la excepción de un directivo.');

    changeGrade(changeCommand(40, ageException: 'Adelantado dos grados'));

    expect($this->scene->enrollments->currentOf(5, 2)?->ageException()?->approvedBy)->toBe(9);
});

it('answers not found for an enrollment it does not have', function () {
    expect(fn () => changeSection(changeCommand(11, enrollment: 99)))->toThrow(EnrollmentNotFound::class);
});

it('refuses whoever may not manage enrollments', function () {
    $this->scene->permissions->manage = false;

    expect(fn () => changeSection(changeCommand(11)))->toThrow(AuthorizationDenied::class)
        ->and(fn () => changeGrade(changeCommand(20)))->toThrow(AuthorizationDenied::class);
});
