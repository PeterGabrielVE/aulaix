<?php

use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\GradedEnrollments;
use AulaX\Enrollment\Application\Port\GradedEnrollment;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Enrollment\FakeEnrollmentPermissions;

/**
 * SPEC-010 MAT-R11.1, R11.4, NF01: the fine permission check of the use
 * cases, and the extension point for grades (MAT-R14.3).
 */
it('lets manage, approve exceptions and view only with each permission', function () {
    $permissions = new FakeEnrollmentPermissions(manage: false, approve: false, view: false);

    expect(fn () => EnrollmentAccess::ensureCanManage($permissions))->toThrow(AuthorizationDenied::class, 'No tienes permiso para gestionar inscripciones.')
        ->and(fn () => EnrollmentAccess::ensureCanApproveExceptions($permissions))->toThrow(AuthorizationDenied::class, 'Solo un directivo puede aprobar esta excepción.')
        ->and(fn () => EnrollmentAccess::ensureCanView($permissions))->toThrow(AuthorizationDenied::class, 'No tienes permiso para ver estudiantes.');

    $allowed = new FakeEnrollmentPermissions;

    expect(function () use ($allowed) {
        EnrollmentAccess::ensureCanManage($allowed);
        EnrollmentAccess::ensureCanApproveExceptions($allowed);
        EnrollmentAccess::ensureCanView($allowed);
    })->not->toThrow(AuthorizationDenied::class);
});

it('treats no enrollment as graded while no module says so', function () {
    $graded = new class implements GradedEnrollment
    {
        public function hasGrades(int $enrollmentId): bool
        {
            return $enrollmentId === 7;
        }
    };

    expect((new GradedEnrollments([]))->hasGrades(7))->toBeFalse()
        ->and((new GradedEnrollments([$graded]))->hasGrades(7))->toBeTrue()
        ->and((new GradedEnrollments([$graded]))->hasGrades(8))->toBeFalse();
});
