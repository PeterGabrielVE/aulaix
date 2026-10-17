<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * SPEC-002 IAM-R08 as rewritten by SPEC-004 IDG-R07: inside an institution,
 * the profile lets the person leave it; the account itself is managed on the
 * central domain.
 */
test('profile page is displayed', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->get(tenantUrl($institution, '/profile'));

    $response->assertOk();
});

test('a member can leave the institution, keeping their account and other memberships', function () {
    $norte = tenant();
    $teacher = userWithRole($norte, 'Docente');
    $sur = tenant();
    joinInstitution($teacher, $sur, 'Docente');

    $this->actingAs($teacher)->post(tenantUrl($sur, '/profile/salir'))
        ->assertRedirect(centralUrl('/planteles'));

    $this->assertGuest();
    expect(DB::table('institution_memberships')->where('user_id', $teacher->id)->value('status'))->toBe('ended')
        ->and($teacher->fresh())->not->toBeNull();

    useTenant($norte);
    expect(DB::table('institution_memberships')->where('user_id', $teacher->id)->value('status'))->toBe('active');
});

test('leaving takes away the roles and the access to that institution', function () {
    $institution = tenant();
    $teacher = userWithRole($institution, 'Docente');
    $this->actingAs($teacher)->post(tenantUrl($institution, '/profile/salir'));

    expect($teacher->fresh()->getRoleNames()->all())->toBe([]);

    $this->actingAs($teacher->fresh())->get(tenantUrl($institution, '/profile'))->assertRedirect(centralUrl('/planteles'));
    expect(DB::table('audit_entries')->where('event_type', 'identity.membership.ended')->exists())->toBeTrue();
});

test('the last active administrador can not leave', function () {
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');

    $this->actingAs($admin)->post(tenantUrl($institution, '/profile/salir'))
        ->assertSessionHasErrors(['roles' => 'El plantel debe tener al menos un Administrador activo.']);

    expect(DB::table('institution_memberships')->where('user_id', $admin->id)->value('status'))->toBe('active');
});

test('leaving the institution and deleting the account are separate actions, in separate places', function () {
    $institution = tenant();
    $user = userWithRole($institution, 'Docente');

    $this->actingAs($user)->get(tenantUrl($institution, '/profile'))
        ->assertInertia(fn ($page) => $page->component('Profile/Edit')->where('accountUrl', centralUrl('/cuenta')));
    $this->actingAs($user)->delete(tenantUrl($institution, '/profile'), ['password' => 'password'])->assertMethodNotAllowed();
    expect($user->fresh())->not->toBeNull();
});

test('someone who left can come back with a new invitation', function () {
    Notification::fake();
    $institution = tenant();
    $admin = userWithRole($institution, 'Administrador');
    $teacher = userWithRole($institution, 'Docente', ['email' => 'luis@example.com']);
    $this->actingAs($teacher)->post(tenantUrl($institution, '/profile/salir'));

    auth()->forgetGuards();
    $this->actingAs($admin)->post(tenantUrl($institution, '/users'), ['name' => 'Luis', 'email' => 'luis@example.com', 'roles' => ['Coordinador']])
        ->assertSessionHasNoErrors();
    leaveTenant();
    $this->actingAs($teacher->fresh())->post(centralUrl("/invitaciones/{$institution->subdomain}/unirme"))->assertRedirect();

    useTenant($institution);
    expect(DB::table('institution_memberships')->where('user_id', $teacher->id)->value('status'))->toBe('active')
        ->and($teacher->fresh()->getRoleNames()->all())->toBe(['Coordinador']);
});
