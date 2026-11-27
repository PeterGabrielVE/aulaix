<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

/**
 * SPEC-007 CAL-R05.4: the permission to change grades of closed terms is
 * granted person by person, only by an Administrador, in one institution.
 */
function setClosedTermPermission(object $actor, object $institution, object $member, bool $allowed): TestResponse
{
    return test()->actingAs($actor)->put(tenantUrl($institution, "/users/{$member->id}/permiso-lapsos-cerrados"), ['allowed' => $allowed]);
}

function holdsClosedTermPermission(object $member): bool
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member->fresh()->hasPermissionTo('editar-notas-lapso-cerrado');
}

test('an administrador grants and withdraws the permission, and it is recorded', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');

    setClosedTermPermission($admin, $institution, $teacher, true)->assertSessionHasNoErrors();

    expect(holdsClosedTermPermission($teacher))->toBeTrue();
    $this->actingAs($admin)->get(tenantUrl($institution, "/users/{$teacher->id}/edit"))
        ->assertInertia(fn ($page) => $page->where('user.canEditClosedTermGrades', true)->where('canGrantClosedTermEditing', true));

    setClosedTermPermission($admin, $institution, $teacher, false)->assertSessionHasNoErrors();

    expect(holdsClosedTermPermission($teacher))->toBeFalse()
        ->and(DB::table('audit_entries')->whereIn('event_type', ['identity.membership.permission_granted', 'identity.membership.permission_revoked'])->count())->toBe(2);
});

test('a director can not grant it, not even to themselves', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');

    setClosedTermPermission($director, $institution, $director, true)
        ->assertSessionHasErrors(['closed_term_grades' => 'Solo un Administrador puede conceder o retirar el permiso de editar notas de lapsos cerrados.']);

    expect(holdsClosedTermPermission($director))->toBeFalse();
    $this->actingAs($director)->get(tenantUrl($institution, "/users/{$director->id}/edit"))
        ->assertInertia(fn ($page) => $page->where('canGrantClosedTermEditing', false));
});

test('the permission only applies in the institution that granted it', function () {
    $norte = tenant();
    $admin = userWithRole($norte, 'Administrador');
    $teacher = userWithRole($norte, 'Docente');
    setClosedTermPermission($admin, $norte, $teacher, true);

    $sur = tenant();
    joinInstitution($teacher, $sur, 'Docente');

    expect(holdsClosedTermPermission($teacher))->toBeFalse();
});

test('leaving the institution withdraws it', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente');
    setClosedTermPermission($admin, $institution, $teacher, true);

    auth()->forgetGuards();
    $this->actingAs($teacher)->post(tenantUrl($institution, '/profile/salir'));

    expect(holdsClosedTermPermission($teacher))->toBeFalse();
});

test('a member of another institution can not be given it', function () {
    $other = tenant();
    $outsider = userWithRole($other, 'Docente');
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    setClosedTermPermission($admin, $institution, $outsider, true)->assertNotFound();
});
