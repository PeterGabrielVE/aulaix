<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $institution = tenant();

    $response = $this->get(tenantUrl($institution, '/login'));

    $response->assertStatus(200);
});

test('unknown subdomains are rejected', function () {
    $response = $this->get('http://does-not-exist.'.config('app.domain').'/login');

    $response->assertStatus(404);
});

test('users can authenticate using the login screen', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this->post(tenantUrl($institution, '/login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->post(tenantUrl($institution, '/login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can not authenticate on another institution\'s subdomain', function () {
    $ownInstitution = tenant();
    $user = User::factory()->for($ownInstitution)->create();

    $otherInstitution = tenant();

    $this->post(tenantUrl($otherInstitution, '/login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this->actingAs($user)->post(tenantUrl($institution, '/logout'));

    $this->assertGuest();
    $response->assertRedirect(route('login', absolute: false));
});

test('inactive users can not authenticate', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->inactive()->create();

    $response = $this->post(tenantUrl($institution, '/login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => __('auth.inactive')]);
});

test('inactive users with a wrong password get the generic error', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->inactive()->create();

    $response = $this->post(tenantUrl($institution, '/login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    // Don't reveal that the account exists (and is deactivated) to someone
    // who doesn't know its password.
    $response->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('a user deactivated mid-session is logged out on their next request', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->actingAs($user);
    $user->update(['status' => User::STATUS_INACTIVE]);

    $response = $this->get(tenantUrl($institution, '/profile'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['email' => __('auth.inactive')]);
});

test('failed logins in one institution do not lock out the same email in another', function () {
    $institutionA = tenant();
    User::factory()->for($institutionA)->create(['email' => 'shared@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post(tenantUrl($institutionA, '/login'), [
            'email' => 'shared@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $institutionB = tenant();
    User::factory()->for($institutionB)->create(['email' => 'shared@example.com']);

    $this->post(tenantUrl($institutionB, '/login'), [
        'email' => 'shared@example.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});
