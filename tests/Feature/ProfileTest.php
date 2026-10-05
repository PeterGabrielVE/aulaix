<?php

use App\Models\User;

test('profile page is displayed', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->get(tenantUrl($institution, '/profile'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->patch(tenantUrl($institution, '/profile'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->patch(tenantUrl($institution, '/profile'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->delete(tenantUrl($institution, '/profile'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl($institution, '/profile'))
        ->delete(tenantUrl($institution, '/profile'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(tenantUrl($institution, '/profile'));

    $this->assertNotNull($user->fresh());
});
