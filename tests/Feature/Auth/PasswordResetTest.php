<?php

use App\Models\Institution;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Request a reset link for $user from their institution's forgot-password
 * form and return the token from the email.
 */
function requestResetAndGetToken(Institution $institution, User $user): string
{
    Notification::fake();

    test()->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email]);

    $token = null;
    Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    return $token;
}

function resetPasswordPayload(User $user, string $token, string $password = 'nueva-contrasena'): array
{
    return [
        'token' => $token,
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ];
}

test('reset password link screen can be rendered', function () {
    $institution = tenant();

    $response = $this->get(tenantUrl($institution, '/forgot-password'));

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($user, ResetPasswordLink::class);
});

test('the reset email links to the institution subdomain and states the 60 minute expiry', function () {
    Notification::fake();

    $institution = tenant(['name' => 'Colegio Los Próceres']);
    $user = User::factory()->for($institution)->create();

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user, $institution) {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toContain('Colegio Los Próceres')
            ->and($mail->actionUrl)->toBe(tenantUrl($institution, "/reset-password/{$notification->token}?email=".urlencode($user->email)))
            ->and($mail->introLines)->toContain('Recibimos una solicitud para restablecer la contraseña de tu cuenta de Colegio Los Próceres en AulaX.')
            ->and($mail->outroLines)->toContain('Este enlace vence en 60 minutos y solo puede usarse una vez.');

        return true;
    });
});

test('an unknown email gets the same answer as a registered one and no email is sent', function () {
    Notification::fake();

    $institution = tenant();
    User::factory()->for(tenant())->create(['email' => 'otra@example.com']);

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => 'otra@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertNothingSent();
});

test('a second request within the broker throttle gets the same answer without sending another email', function () {
    Notification::fake();

    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email]);
    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentToTimes($user, ResetPasswordLink::class, 1);
});

test('the forgot password form is rate limited per client', function () {
    Notification::fake();

    $institution = tenant();

    foreach (range(1, 6) as $attempt) {
        $this->post(tenantUrl($institution, '/forgot-password'), ['email' => "nadie{$attempt}@example.com"])
            ->assertRedirect();
    }

    $this->post(tenantUrl($institution, '/forgot-password'), ['email' => 'nadie7@example.com'])
        ->assertTooManyRequests();
});

test('reset password screen can be rendered', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    $token = requestResetAndGetToken($institution, $user);

    $this->get(tenantUrl($institution, "/reset-password/{$token}?email={$user->email}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('email', $user->email)
            ->where('token', $token)
        );
});

test('password can be reset with valid token', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    $token = requestResetAndGetToken($institution, $user);

    $this->post(tenantUrl($institution, '/reset-password'), resetPasswordPayload($user, $token))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('nueva-contrasena', $user->fresh()->password))->toBeTrue();
});

test('a reset link still works 59 minutes after it was requested', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    $token = requestResetAndGetToken($institution, $user);

    $this->travel(59)->minutes();

    $this->post(tenantUrl($institution, '/reset-password'), resetPasswordPayload($user, $token))
        ->assertSessionHasNoErrors();

    expect(Hash::check('nueva-contrasena', $user->fresh()->password))->toBeTrue();
});

test('a reset link expires after 60 minutes', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    $token = requestResetAndGetToken($institution, $user);

    $this->travel(61)->minutes();

    $this->post(tenantUrl($institution, '/reset-password'), resetPasswordPayload($user, $token))
        ->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a reset link can only be used once', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    $token = requestResetAndGetToken($institution, $user);

    $this->post(tenantUrl($institution, '/reset-password'), resetPasswordPayload($user, $token, 'primera-contrasena'))
        ->assertSessionHasNoErrors();

    $this->post(tenantUrl($institution, '/reset-password'), resetPasswordPayload($user, $token, 'segunda-contrasena'))
        ->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('primera-contrasena', $user->fresh()->password))->toBeTrue();
});

test('an invalid reset token is rejected', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();
    requestResetAndGetToken($institution, $user);

    $this->post(tenantUrl($institution, '/reset-password'), resetPasswordPayload($user, 'not-the-token'))
        ->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
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
    Notification::assertSentTo($userA, ResetPasswordLink::class, function ($notification) use (&$tokenA) {
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

    Notification::assertSentTo($userA, ResetPasswordLink::class, function ($notification) use ($institutionB) {
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
