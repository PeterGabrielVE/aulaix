<?php

use App\Models\User;

test('confirm password screen can be rendered', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this->actingAs($user)->get(tenantUrl($institution, '/confirm-password'));

    $response->assertStatus(200);
});

test('password can be confirmed', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this->actingAs($user)->post(tenantUrl($institution, '/confirm-password'), [
        'password' => 'password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

test('password is not confirmed with invalid password', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $response = $this->actingAs($user)->post(tenantUrl($institution, '/confirm-password'), [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
});
