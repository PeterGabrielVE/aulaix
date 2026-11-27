<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-004 IDG-R03.8, R03.9: access to an institution follows the membership
 * there, checked on every request.
 */
function setMembership(User $user, array $values): void
{
    DB::table('institution_memberships')->where('user_id', $user->id)->update($values);
}

test('a suspended or ended membership ends the session in that institution', function (array $membership) {
    $institution = tenant();
    $teacher = userWithRole($institution, 'Docente');
    $this->actingAs($teacher)->get(tenantUrl($institution, '/profile'))->assertOk();

    setMembership($teacher, $membership);

    $this->get(tenantUrl($institution, '/profile'))
        ->assertRedirect(centralUrl('/planteles'));
    $this->assertGuest();
})->with([
    'suspended' => [['status' => 'suspended', 'suspension_reason' => 'institution']],
    'ended' => [['status' => 'ended', 'ended_at' => now()]],
]);

test('an account that is no longer active loses access everywhere', function () {
    $institution = tenant();
    $teacher = userWithRole($institution, 'Docente');
    DB::table('users')->where('id', $teacher->id)->update(['account_state' => 'pending_deletion']);

    $this->actingAs($teacher)->get(tenantUrl($institution, '/profile'))->assertRedirect(centralUrl('/planteles'));
    $this->assertGuest();
});

test('a suspension in one institution leaves the others untouched', function () {
    $institutionA = tenant();
    $teacher = userWithRole($institutionA, 'Docente');
    $institutionB = tenant();
    joinInstitution($teacher, $institutionB, 'Docente');
    setMembership($teacher, ['status' => 'suspended', 'suspension_reason' => 'institution']);

    $this->actingAs($teacher)->get(tenantUrl($institutionB, '/profile'))->assertRedirect(centralUrl('/planteles'));

    auth()->forgetGuards();
    $this->actingAs($teacher->fresh())->get(tenantUrl($institutionA, '/profile'))->assertOk();
});

test('logging in from an institution without membership never opens it', function () {
    $elsewhere = tenant();
    User::factory()->for($elsewhere)->inactive()->create(['email' => 'ana@example.com', 'password' => 'secreta-123']);
    $institution = tenant();

    // Only the central welcome: neither this institution nor the suspended one.
    logInTo($institution, 'ana@example.com', 'secreta-123')->assertRedirect(centralUrl('/planteles'));
});

test('a session can not be carried into an institution without membership', function () {
    $elsewhere = tenant();
    $teacher = userWithRole($elsewhere, 'Docente');
    $institution = tenant();

    $session = ['login_web_'.sha1(SessionGuard::class) => $teacher->id];

    // Row Level Security hides the account here, so the guard finds nobody.
    $this->withSession($session)->get(tenantUrl($institution, '/profile'))->assertRedirect(route('login'));
    $this->withSession($session)->get(tenantUrl($elsewhere, '/profile'))->assertOk();
});
