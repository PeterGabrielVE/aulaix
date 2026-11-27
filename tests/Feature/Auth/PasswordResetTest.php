<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\ConsolidationGuidance;
use AulaX\IdentityAccess\Infrastructure\Notifications\PasswordChangedNotice;
use AulaX\IdentityAccess\Infrastructure\Notifications\ResetPasswordLink;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-002 IAM-R05 as kept by SPEC-004 IDG-R06: password recovery, now on
 * the central domain and for the whole account.
 */
function requestReset(string $email): TestResponse
{
    leaveTenant();

    return test()->post(centralUrl('/forgot-password'), ['email' => $email]);
}

/**
 * Request a reset link and return the token from the email.
 */
function requestResetAndGetToken(User $user): string
{
    Notification::fake();
    requestReset($user->email);

    $token = null;
    Notification::assertSentOnDemand(ResetPasswordLink::class, function (ResetPasswordLink $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    return $token;
}

function resetPassword(User $user, string $token, string $password = 'nueva-contrasena'): TestResponse
{
    leaveTenant();

    return test()->post(centralUrl('/reset-password'), [
        'token' => $token,
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

test('reset password link screen can be rendered', function () {
    $this->get(centralUrl('/forgot-password'))->assertOk();
});

test('an institution\'s forgot password screen is the central one', function () {
    $institution = tenant();

    $this->get(tenantUrl($institution, '/forgot-password'))->assertRedirect(centralUrl('/forgot-password'));
});

test('reset password link can be requested', function () {
    Notification::fake();
    $user = User::factory()->for(tenant())->create();

    requestReset($user->email)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentOnDemand(ResetPasswordLink::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === $user->email);
});

test('the reset email links to the central domain and states the 60 minute expiry', function () {
    Notification::fake();
    $user = User::factory()->for(tenant())->create(['name' => 'Ana Pérez']);

    requestReset($user->email);

    Notification::assertSentOnDemand(ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user) {
        $mail = $notification->toMail(new stdClass);

        expect($mail->actionUrl)->toBe(centralUrl("/reset-password/{$notification->token}?email=".urlencode($user->email)))
            ->and($mail->greeting)->toBe('¡Hola, Ana Pérez!')
            ->and($mail->outroLines)->toContain('Este enlace vence en 60 minutos y solo puede usarse una vez.');

        return true;
    });
});

test('an unknown email gets the same answer as a registered one and no email is sent', function () {
    Notification::fake();

    requestReset('nadie@example.com')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertNothingSent();
});

test('a second request within the broker throttle gets the same answer without sending another email', function () {
    Notification::fake();
    $user = User::factory()->for(tenant())->create();

    requestReset($user->email);
    requestReset($user->email)
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentOnDemandTimes(ResetPasswordLink::class, 1);
});

test('the forgot password form is rate limited per client', function () {
    Notification::fake();

    foreach (range(1, 6) as $attempt) {
        requestReset("nadie{$attempt}@example.com")->assertRedirect();
    }

    requestReset('nadie7@example.com')->assertTooManyRequests();
});

test('reset password screen can be rendered', function () {
    $user = User::factory()->for(tenant())->create();
    $token = requestResetAndGetToken($user);

    $this->get(centralUrl("/reset-password/{$token}?email={$user->email}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ResetPassword')
            ->where('email', $user->email)
            ->where('token', $token)
        );
});

test('password can be reset with valid token', function () {
    $user = User::factory()->for(tenant())->create();
    $token = requestResetAndGetToken($user);

    resetPassword($user, $token)
        ->assertSessionHasNoErrors()
        ->assertRedirect(centralUrl('/login'));

    expect(Hash::check('nueva-contrasena', $user->fresh()->password))->toBeTrue();
});

test('the new password is the account\'s, in every institution', function () {
    $norte = tenant();
    $user = userWithRole($norte, 'Docente');
    $sur = tenant();
    joinInstitution($user, $sur, 'Docente');
    resetPassword($user, requestResetAndGetToken($user));

    logInTo($sur, $user->email, 'nueva-contrasena')->assertRedirect(tenantUrl($sur, '/dashboard'));
});

test('resetting the password closes every session and tells the owner', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->for(tenant())->create();
    $token = requestResetAndGetToken($user);
    DB::table('sessions')->insert(['id' => 'stolen', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    resetPassword($user, $token)->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
    Notification::assertSentOnDemand(PasswordChangedNotice::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === $user->email);
});

test('a password reset is recorded in the platform log, without the password', function () {
    $user = User::factory()->for(tenant())->create();
    resetPassword($user, requestResetAndGetToken($user));

    $entry = DB::table('platform_audit_entries')->sole();
    expect($entry->event_type)->toBe('identity.account.password_changed')
        ->and($entry->subject_id)->toBe((string) $user->id)
        ->and($entry->changes)->toBe('{}');
});

test('a reset link still works 59 minutes after it was requested', function () {
    $user = User::factory()->for(tenant())->create();
    $token = requestResetAndGetToken($user);

    $this->travel(59)->minutes();

    resetPassword($user, $token)->assertSessionHasNoErrors();

    expect(Hash::check('nueva-contrasena', $user->fresh()->password))->toBeTrue();
});

test('a reset link expires after 60 minutes', function () {
    $user = User::factory()->for(tenant())->create();
    $token = requestResetAndGetToken($user);

    $this->travel(61)->minutes();

    resetPassword($user, $token)->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a reset link can only be used once', function () {
    $user = User::factory()->for(tenant())->create();
    $token = requestResetAndGetToken($user);

    resetPassword($user, $token, 'primera-contrasena')->assertSessionHasNoErrors();
    resetPassword($user, $token, 'segunda-contrasena')->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('primera-contrasena', $user->fresh()->password))->toBeTrue();
});

test('an invalid reset token is rejected', function () {
    $user = User::factory()->for(tenant())->create();
    requestResetAndGetToken($user);

    resetPassword($user, 'not-the-token')->assertSessionHasErrors(['email' => __('passwords.token')]);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('reset tokens are never readable from an institution', function () {
    $user = User::factory()->for($institution = tenant())->create();
    requestResetAndGetToken($user);

    useTenant($institution);

    expect(DB::table('password_reset_tokens')->count())->toBe(0);
});

test('an email awaiting consolidation gets guidance instead of a reset link', function () {
    Notification::fake();
    User::factory()->for(tenant())->create(['email' => 'ana@example.com', 'consolidation_state' => 'pending']);
    User::factory()->for(tenant())->create(['email' => 'ana@example.com', 'consolidation_state' => 'pending']);

    requestReset('ana@example.com')->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentOnDemandTimes(ResetPasswordLink::class, 0);
    Notification::assertSentOnDemandTimes(ConsolidationGuidance::class, 1);
});
