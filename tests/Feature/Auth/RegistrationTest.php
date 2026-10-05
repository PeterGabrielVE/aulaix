<?php

/*
 * Accounts are created by each institution's administrator and activated
 * by invitation (see UserManagementTest / InvitationTest) — there is no
 * self-service sign-up.
 */

test('there is no public registration screen', function () {
    $institution = tenant();

    $this->get(tenantUrl($institution, '/register'))->assertNotFound();
});

test('there is no public registration endpoint', function () {
    $institution = tenant();

    $this->post(tenantUrl($institution, '/register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
});
