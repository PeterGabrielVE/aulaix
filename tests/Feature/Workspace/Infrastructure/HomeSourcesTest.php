<?php

use AulaX\Workspace\Application\Port\StaffSource;
use AulaX\Workspace\Application\Port\StudentSource;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Workspace/home_helpers.php';

/**
 * SPEC-013 NAV-R05.1, R05.4, R05.5, R08: the home screen's figures, read
 * through the public queries of Students and IdentityAccess.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->director = userWithRole($this->institution, 'Director');
    useTenant($this->institution);
    $this->actingAs($this->director);
});

test('counts the active students and the requests waiting for an answer', function () {
    $ana = seedStudent($this->institution->id, 'V11111111111');
    seedStudent($this->institution->id, 'V22222222222');
    seedStudent($this->institution->id, 'V33333333333', 'withdrawn');
    $guardian = seedGuardian($this->institution->id, null, 'V15432100');
    seedCorrection($this->institution->id, $guardian, $ana);
    seedCorrection($this->institution->id, $guardian, $ana, 'resolved');

    $students = app(StudentSource::class);

    expect($students->activeStudents())->toBe(2)
        ->and($students->pendingRequests())->toBe(['transfers' => 0, 'corrections' => 1]);
});

test('gives a guardian its students and corrections waiting, or nothing before any link', function () {
    $parent = userWithRole($this->institution, 'Representante');
    useTenant($this->institution);
    $ana = seedStudent($this->institution->id, 'V11111111111');
    $guardian = seedGuardian($this->institution->id, $parent->id, 'V15432100');
    seedGuardianship($this->institution->id, $ana, $guardian);
    seedCorrection($this->institution->id, $guardian, $ana);
    $unlinked = userWithRole($this->institution, 'Representante');

    $students = app(StudentSource::class);

    expect($students->representedBy($parent->id))->toBe(['students' => [['id' => $ana, 'name' => 'Pérez, Ana']], 'pendingCorrections' => 1])
        ->and($students->representedBy($unlinked->id))->toBe(['students' => [], 'pendingCorrections' => 0]);
});

test('counts the active members by role and the invitations, valid or expired', function () {
    userWithRole($this->institution, 'Docente');
    userWithRole($this->institution, 'Docente');
    $gone = userWithRole($this->institution, 'Docente');
    useTenant($this->institution);
    DB::table('institution_memberships')->where('user_id', $gone->id)->update(['status' => 'ended']);
    seedInvitation($this->institution->id, 'nuevo@correo.ve', now()->addDays(3));
    seedInvitation($this->institution->id, 'viejo@correo.ve', now()->subDay());

    $staff = app(StaffSource::class);

    expect($staff->membersByRole())->toBe(['Administrador' => 0, 'Director' => 1, 'Coordinador' => 0, 'Docente' => 2, 'Representante' => 0, 'Estudiante' => 0])
        ->and($staff->invitations())->toBe(['pending' => 1, 'expired' => 1]);
});
