<?php

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Send $user an invitation and return the token from the email.
 */
function inviteAndGetToken(User $user): string
{
    Notification::fake();

    $user->sendInvitation();

    $token = null;
    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return $token;
}

test('the invitation screen can be rendered', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->unverified()->create();
    $token = inviteAndGetToken($user);

    $this->get(tenantUrl($institution, "/invitation/{$token}?email={$user->email}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/AcceptInvitation')
            ->where('email', $user->email)
        );
});

test('accepting an invitation sets the password, verifies the email and logs the user in', function () {
    $institution = tenant();
    $user = userWithRole($institution, 'Docente', ['email_verified_at' => null]);
    $token = inviteAndGetToken($user);

    $response = $this->post(tenantUrl($institution, '/invitation'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'mi-contrasena-nueva',
        'password_confirmation' => 'mi-contrasena-nueva',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $user->refresh();
    expect(Hash::check('mi-contrasena-nueva', $user->password))->toBeTrue();
    expect($user->hasAcceptedInvitation())->toBeTrue();
});

test('an invitation is still valid days later, unlike a password reset link', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->unverified()->create();
    $token = inviteAndGetToken($user);

    $this->travel(6)->days();

    $this->post(tenantUrl($institution, '/invitation'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'mi-contrasena-nueva',
        'password_confirmation' => 'mi-contrasena-nueva',
    ])->assertSessionHasNoErrors();
});

test('an invitation expires after a week', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->unverified()->create();
    $token = inviteAndGetToken($user);

    $this->travel(8)->days();

    $this->post(tenantUrl($institution, '/invitation'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'mi-contrasena-nueva',
        'password_confirmation' => 'mi-contrasena-nueva',
    ])->assertSessionHasErrors(['email' => __('passwords.token')]);

    $this->assertGuest();
});

test('an invalid invitation token is rejected', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->unverified()->create();
    inviteAndGetToken($user);

    $this->post(tenantUrl($institution, '/invitation'), [
        'token' => 'not-the-token',
        'email' => $user->email,
        'password' => 'mi-contrasena-nueva',
        'password_confirmation' => 'mi-contrasena-nueva',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('a deactivated user who accepts an invitation is not logged in', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->unverified()->inactive()->create();
    $token = inviteAndGetToken($user);

    $response = $this->post(tenantUrl($institution, '/invitation'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'mi-contrasena-nueva',
        'password_confirmation' => 'mi-contrasena-nueva',
    ]);

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
