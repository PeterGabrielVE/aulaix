<?php

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\Enrollment\Application\EnrollStudent\EnrollmentResult;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Domain\AgeOutOfRange;
use AulaX\Enrollment\Domain\AlreadyEnrolled;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\OverCapacity;
use AulaX\Enrollment\Domain\PromotionResult;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Application\StudentNotFound;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R01.1 – R01.6, R02.1, R02.2, R03.1 – R03.5, R12.1, R13.3:
 * enrolling a student, under the calendar lock and recorded.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
});

function enrollStudent(int $student = 5, int $section = 10, int $year = 2, ?string $date = '2026-09-16', ?string $overCapacity = null, ?string $ageException = null, int $actor = 3): EnrollmentResult
{
    $scene = test()->scene;

    return (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, $year, $section, $date, $overCapacity, $ageException, $actor),
    );
}

it('enrolls a student in a section of the year, under the calendar lock, recorded inside the transaction', function () {
    $result = enrollStudent();
    $enrollment = $this->scene->enrollments->current()[0];

    expect($result->ageWarning)->toBeNull()
        ->and($enrollment->id()?->value)->toBe($result->enrollmentId)
        ->and($enrollment->studentId())->toBe(5)
        ->and($enrollment->placement()->sectionId)->toBe(10)
        ->and($enrollment->placement()->grade)->toBe('primaria_3')
        ->and($enrollment->registeredBy())->toBe(3)
        ->and($enrollment->registeredAt()->format('Y-m-d H:i'))->toBe('2026-10-15 09:00')
        ->and($this->scene->calendar->lockedInsideTransaction)->toBe(1)
        ->and($this->scene->audit->types())->toBe(['enrollment.enrollment.enrolled'])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('refuses a second current enrollment of the student in the year, saying where it is', function () {
    enrollStudent();

    expect(fn () => enrollStudent(section: 11))->toThrow(AlreadyEnrolled::class, 'El estudiante ya está inscrito en 3.er grado sección A en este año escolar.');
});

it('refuses a withdrawn student, a closed year, a section of another year and a date after today', function () {
    $this->scene->students->add(6, '2018-01-01', active: false);
    $this->scene->calendar->year(1, 2025, 'closed');
    $this->scene->structure->section(5, 1, 'primaria_3');

    expect(fn () => enrollStudent(student: 6))->toThrow(BusinessRuleViolation::class, 'El estudiante está retirado: reincorpóralo primero.')
        ->and(fn () => enrollStudent(section: 5, year: 1))->toThrow(BusinessRuleViolation::class, 'El año escolar está cerrado.')
        ->and(fn () => enrollStudent(section: 5))->toThrow(BusinessRuleViolation::class, 'La sección no es de ese año escolar.')
        ->and(fn () => enrollStudent(date: '2026-10-16'))->toThrow(BusinessRuleViolation::class, 'La fecha de inscripción no puede ser posterior a hoy.')
        ->and(fn () => enrollStudent(date: '16/09/2026'))->toThrow(BusinessRuleViolation::class, 'Indica una fecha válida.')
        ->and($this->scene->enrollments->enrollments)->toBe([]);
});

it('answers not found for a student or a year it does not have', function () {
    expect(fn () => enrollStudent(student: 99))->toThrow(StudentNotFound::class)
        ->and(fn () => enrollStudent(year: 9))->toThrow(SchoolYearNotFound::class);
});

it('over the capacity, enrolls only with a reason, and keeps it', function () {
    $this->scene->structure->sections[2][0] = new SectionFact(10, 2, 'primaria_3', '3.er grado', 'primaria', 'A', 1);
    $this->scene->students->add(6, '2018-01-01');
    enrollStudent(student: 6);

    expect(fn () => enrollStudent())->toThrow(OverCapacity::class, 'La sección tiene 1 inscritos y cupo 1: indica el motivo para superarlo.');

    enrollStudent(overCapacity: 'Hermano en la sección');

    expect($this->scene->enrollments->currentOf(5, 2)?->overCapacityReason())->toBe('Hermano en la sección');
});

it('warns when the age is not the regular one of the grade, inside the range', function () {
    $this->scene->students->add(6, '2019-05-01');

    expect(enrollStudent(student: 6)->ageWarning)->toBe('Tiene 7 años; la edad regular de 3.er grado es 8.');
});

it('out of the age range, enrolls only with the exception of a directive, who is the person signed in', function () {
    $this->scene->students->add(6, '2021-05-01');

    expect(fn () => enrollStudent(student: 6))->toThrow(AgeOutOfRange::class, 'Tiene 5 años y el rango del grado es de 7 a 9 años: hace falta la excepción de un directivo.');

    $this->scene->permissions->approve = false;

    expect(fn () => enrollStudent(student: 6, ageException: 'Adelantado'))->toThrow(BusinessRuleViolation::class, 'Solo un directivo puede aprobar una inscripción fuera del rango de edad.');

    $this->scene->permissions->approve = true;
    enrollStudent(student: 6, ageException: 'Adelantado por evaluación', actor: 9);
    $exception = $this->scene->enrollments->currentOf(6, 2)?->ageException();

    expect($exception?->justification)->toBe('Adelantado por evaluación')
        ->and($exception?->approvedBy)->toBe(9);
});

it('ignores an exception that is not needed', function () {
    $this->scene->permissions->approve = false;
    enrollStudent(ageException: 'Por si acaso');

    expect($this->scene->enrollments->currentOf(5, 2)?->ageException())->toBeNull();
});

it('refuses whoever may not manage enrollments', function () {
    $this->scene->permissions->manage = false;

    expect(fn () => enrollStudent())->toThrow(AuthorizationDenied::class)
        ->and($this->scene->enrollments->enrollments)->toBe([]);
});

it('a student waiting to graduate is not enrolled in a grade (MAT-R15.4)', function () {
    $this->scene->structure->section(50, 2, 'media_5', 'A');
    $this->scene->students->add(6, '2010-03-10');
    $last = enrollStudent(student: 6, section: 50)->enrollmentId;
    $enrollment = $this->scene->enrollments->byId(EnrollmentId::fromInt($last));
    $enrollment->recordResult(PromotionResult::of(Outcome::PromotedWithPending, [4]), [4], new DateTimeImmutable('2027-07-10'), 3);
    $this->scene->enrollments->save($enrollment);
    $this->scene->calendar->year(3, 2027, 'planned');
    $this->scene->structure->section(60, 3, 'media_5', 'A');
    $this->scene->clock->now = new DateTimeImmutable('2027-09-20 09:00');

    expect(fn () => enrollStudent(student: 6, section: 60, year: 3, date: '2027-09-16'))
        ->toThrow(BusinessRuleViolation::class, 'Está pendiente de egresar: solo cursa sus materias pendientes.');
});
