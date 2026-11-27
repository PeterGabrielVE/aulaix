<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPEC-010 MAT-R11.3: an Administrador lets other members enroll students,
 * person by person, in one institution.
 */
function setEnrollmentManagement(object $actor, object $institution, object $member, bool $allowed): TestResponse
{
    return test()->actingAs($actor)->put(tenantUrl($institution, "/users/{$member->id}/permiso-inscripciones"), ['allowed' => $allowed]);
}

function managesEnrollments(object $member): bool
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member->fresh()->hasPermissionTo('gestionar-inscripciones');
}

test('an administrador grants and withdraws it to a member, recorded', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $secretary = userWithRole($institution, 'Docente');

    setEnrollmentManagement($admin, $institution, $secretary, true)->assertSessionHasNoErrors();

    expect(managesEnrollments($secretary))->toBeTrue();
    $this->actingAs($admin)->get(tenantUrl($institution, "/users/{$secretary->id}/edit"))
        ->assertInertia(fn ($page) => $page->where('user.canManageEnrollments', true)->where('canGrantEnrollmentManagement', true));

    setEnrollmentManagement($admin, $institution, $secretary, false)->assertSessionHasNoErrors();

    expect(managesEnrollments($secretary))->toBeFalse()
        ->and(DB::table('audit_entries')->whereIn('event_type', ['identity.membership.permission_granted', 'identity.membership.permission_revoked'])->count())->toBe(2);
});

test('only an administrador grants it', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $teacher = userWithRole($institution, 'Docente');

    setEnrollmentManagement($director, $institution, $teacher, true)
        ->assertSessionHasErrors(['enrollment_management' => 'Solo un Administrador puede conceder o retirar el permiso de gestionar inscripciones.']);

    expect(managesEnrollments($teacher))->toBeFalse();
    $this->actingAs($director)->get(tenantUrl($institution, "/users/{$teacher->id}/edit"))
        ->assertInertia(fn ($page) => $page->where('canGrantEnrollmentManagement', false));
});

test('it only applies in the institution that granted it', function () {
    $norte = tenant();
    $admin = userWithRole($norte, 'Administrador');
    $teacher = userWithRole($norte, 'Docente');
    setEnrollmentManagement($admin, $norte, $teacher, true);

    $sur = tenant();
    joinInstitution($teacher, $sur, 'Docente');

    expect(managesEnrollments($teacher))->toBeFalse();
});
