<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $institution = tenant();

    $response = $this->get(tenantUrl($institution, '/forgot-password'));

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($institution) {
        $response = $this->get(tenantUrl($institution, '/reset-password/'.$notification->token));

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $institution) {
        $response = $this->post(tenantUrl($institution, '/reset-password'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('reset tokens for the same email are kept separately per institution', function () {
    Notification::fake();

    $institutionA = tenant();
    $userA = User::factory()->for($institutionA)->create(['email' => 'shared@example.com']);
    $this->post(tenantUrl($institutionA, '/forgot-password'), ['email' => 'shared@example.com']);

    $institutionB = tenant();
    $userB = User::factory()->for($institutionB)->create(['email' => 'shared@example.com']);
    $this->post(tenantUrl($institutionB, '/forgot-password'), ['email' => 'shared@example.com']);

    $tokenA = null;
    Notification::assertSentTo($userA, ResetPassword::class, function ($notification) use (&$tokenA) {
        $tokenA = $notification->token;

        return true;
    });

    // Requesting a reset in B used to overwrite A's pending token (the table
    // was keyed by email alone). A's link must still work.
    $this->post(tenantUrl($institutionA, '/reset-password'), [
        'token' => $tokenA,
        'email' => 'shared@example.com',
        'password' => 'new-password-a',
        'password_confirmation' => 'new-password-a',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('new-password-a', $userA->fresh()->password))->toBeTrue();
});

test('a reset token from one institution can not be used in another', function () {
    Notification::fake();

    $institutionA = tenant();
    $userA = User::factory()->for($institutionA)->create(['email' => 'shared@example.com']);
    $this->post(tenantUrl($institutionA, '/forgot-password'), ['email' => 'shared@example.com']);

    $institutionB = tenant();
    $userB = User::factory()->for($institutionB)->create(['email' => 'shared@example.com']);

    Notification::assertSentTo($userA, ResetPassword::class, function ($notification) use ($institutionB) {
        $this->post(tenantUrl($institutionB, '/reset-password'), [
            'token' => $notification->token,
            'email' => 'shared@example.com',
            'password' => 'hijacked-password',
            'password_confirmation' => 'hijacked-password',
        ])->assertSessionHasErrors('email');

        return true;
    });

    expect(Hash::check('password', $userB->fresh()->password))->toBeTrue();
});
