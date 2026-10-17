<?php

use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-002 IAM-R02 / R03 as rewritten by SPEC-004 (IDG-R03): an
 * institution's login screen is the central one.
 */
test('the login screen of an institution is the central one', function () {
    $institution = tenant();

    $this->get(tenantUrl($institution, '/login'))
        ->assertRedirect(centralUrl("/login?plantel={$institution->subdomain}"));
});

test('unknown subdomains are rejected', function () {
    $response = $this->get('http://does-not-exist.'.config('app.domain').'/login');

    $response->assertStatus(404);
});

test('users who log in from an institution land in it', function () {
    $norte = tenant();
    $user = userWithRole($norte, 'Docente');
    $sur = tenant();
    joinInstitution($user, $sur, 'Docente');

    // Two institutions, but they came from Sur's subdomain: no selector.
    logInTo($sur, $user->email)->assertRedirect(tenantUrl($sur, '/dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    logInTo($institution, $user->email, 'wrong-password')->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('logging in from an institution without membership does not open it', function () {
    $ownInstitution = tenant();
    $user = User::factory()->for($ownInstitution)->create();
    $otherInstitution = tenant();

    logInTo($otherInstitution, $user->email)->assertRedirect(tenantUrl($ownInstitution, '/dashboard'));
});

test('a suspended membership is not opened after login', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->inactive()->create();

    logInTo($institution, $user->email)->assertRedirect(centralUrl('/planteles'));

    $this->get(centralUrl('/planteles'))->assertInertia(fn ($page) => $page->where('institutions', []));
});

test('inactive users with a wrong password get the generic error', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->inactive()->create();

    // Don't reveal that the account exists to someone who doesn't know its password.
    logInTo($institution, $user->email, 'wrong-password')->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('a user deactivated mid-session is logged out on their next request', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->actingAs($user);
    DB::table('institution_memberships')->where('user_id', $user->id)->update(['status' => 'suspended', 'suspension_reason' => 'institution']);

    $response = $this->get(tenantUrl($institution, '/profile'));

    $this->assertGuest();
    $response->assertRedirect(centralUrl('/planteles'));
});

test('five failed logins lock the account out for a minute, even with the right password', function () {
    $this->freezeTime();
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    foreach (range(1, 5) as $attempt) {
        logInTo($institution, $user->email, 'wrong-password');
    }

    logInTo($institution, $user->email)->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60])]);

    $this->assertGuest();
});

test('failed logins lock the email out whatever institution it is used for', function () {
    // SPEC-004: the same person is one account, so the lockout is too (IDG-R03.10).
    $institutionA = tenant();
    $person = User::factory()->for($institutionA)->create(['email' => 'shared@example.com']);
    $institutionB = tenant();
    joinInstitution($person, $institutionB);

    foreach (range(1, 5) as $attempt) {
        logInTo($institutionA, 'shared@example.com', 'wrong-password');
    }

    logInTo($institutionB, 'shared@example.com')->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('users can logout', function () {
    config(['session.driver' => 'database']);
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    // Their sessions on the central domain and in another institution.
    DB::table('sessions')->insert([
        ['id' => 'central', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'other-institution', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
    ]);

    $response = $this->actingAs($user)->post(tenantUrl($institution, '/logout'));

    $this->assertGuest();
    $response->assertRedirect(centralUrl('/login'));
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

test('logging out on the central domain ends the institutions\' sessions too', function () {
    config(['session.driver' => 'database']);
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    DB::table('sessions')->insert(['id' => 'institution', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    leaveTenant();

    $this->actingAs($user)->post(centralUrl('/logout'))->assertRedirect(centralUrl('/login'));

    $this->assertGuest();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});
