<?php

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentCommand;
use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentHandler;
use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Roster\YearOverviewQuery;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Application\StudentNotFound;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

pest()->group('rls');

/**
 * SPEC-010 MAT-R13.1 – R13.3, NF02: an institution sees and changes only
 * its own enrollments; the ids of another one do not exist for it.
 */
beforeEach(function () {
    $this->norte = tenant();
    $this->admin = userWithRole($this->norte, 'Administrador');
    $this->actingAs($this->admin);
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year);
    $this->student = enrollmentStudent();
    $this->enrollment = enrollmentRow($this->student, $this->year, $this->section);
});

it('another institution sees none of its enrollments', function () {
    $sur = tenant();
    $this->actingAs(userWithRole($sur, 'Administrador'));

    expect(DB::table('enrollments')->count())->toBe(0)
        ->and(app(EnrollmentRepository::class)->byId(EnrollmentId::fromInt($this->enrollment)))->toBeNull()
        ->and(app(EnrollmentRepository::class)->currentCountIn($this->section))->toBe(0);
});

it('another institution can not enroll its student nor use its section', function () {
    $sur = tenant();
    $this->actingAs(userWithRole($sur, 'Administrador'));
    $year = enrollmentYear();
    $own = enrollmentSection($year);

    expect(fn () => app(EnrollStudentHandler::class)(new EnrollStudentCommand($this->student, $year, $own, '2026-09-16', null, null, 1)))
        ->toThrow(StudentNotFound::class);

    $mine = enrollmentStudent('Mora', 'Eva');

    expect(fn () => app(EnrollStudentHandler::class)(new EnrollStudentCommand($mine, $year, $this->section, '2026-09-16', null, null, 1)))
        ->toThrow(BusinessRuleViolation::class, 'La sección no es de ese año escolar.')
        ->and(fn () => app(YearOverviewQuery::class)($this->year))->toThrow(SchoolYearNotFound::class);
});

it('an enrollment of another institution is not found', function () {
    $sur = tenant();
    $this->actingAs(userWithRole($sur, 'Administrador'));

    expect(fn () => app(AnnulEnrollmentHandler::class)(
        new AnnulEnrollmentCommand($this->enrollment, 'duplicate', null, 1),
    ))->toThrow(EnrollmentNotFound::class);
});
