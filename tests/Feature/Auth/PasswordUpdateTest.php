<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('password can be updated', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl($institution, '/profile'))
        ->put(tenantUrl($institution, '/password'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantUrl($institution, '/profile'));

    $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
});

test('correct password must be provided to update password', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this
        ->actingAs($user)
        ->from(tenantUrl($institution, '/profile'))
        ->put(tenantUrl($institution, '/password'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(tenantUrl($institution, '/profile'));
});
