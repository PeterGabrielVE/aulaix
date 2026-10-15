<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\InvitationMail;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\IdentityAccess\Infrastructure\Persistence\InvitationModel;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

/**
 * SPEC-004 IDG-R04.4, R04.9: accepting an invitation on the central domain.
 */
function invitationToken(Institution $institution, string $email, array $roles = ['Docente']): string
{
    Notification::fake();

    test()->actingAs(userWithRole($institution, 'Administrador'))
        ->post(tenantUrl($institution, '/users'), ['name' => 'Ana Pérez', 'email' => $email, 'roles' => $roles])
        ->assertSessionHasNoErrors();

    $url = null;
    Notification::assertSentOnDemand(InvitationMail::class, function (InvitationMail $mail) use (&$url) {
        $url = $mail->url;

        return true;
    });
    leaveTenant();

    return basename(parse_url($url, PHP_URL_PATH));
}

function accept(Institution $institution, string $token, string $email = 'ana@example.com'): TestResponse
{
    return test()->post(centralUrl("/invitaciones/{$institution->subdomain}"), [
        'token' => $token,
        'email' => $email,
        'password' => 'mi-contrasena-nueva',
        'password_confirmation' => 'mi-contrasena-nueva',
    ]);
}

test('the invitation screen can be rendered', function () {
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com');

    $this->get(centralUrl("/invitaciones/{$institution->subdomain}/{$token}?email=ana@example.com"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/AcceptInvitation')
            ->where('email', 'ana@example.com')
            ->where('subdomain', $institution->subdomain)
        );
});

test('accepting an invitation creates the account, verifies the email and joins the institution', function () {
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com', ['Docente', 'Representante']);

    accept($institution, $token)
        ->assertSessionHasNoErrors()
        ->assertRedirect(centralUrl("/invitaciones/{$institution->subdomain}/aceptada"));

    $account = User::query()->where('email', 'ana@example.com')->sole();
    expect($account->name)->toBe('Ana Pérez')
        ->and(Hash::check('mi-contrasena-nueva', $account->password))->toBeTrue()
        ->and($account->email_verified_at)->not->toBeNull();

    useTenant($institution);
    expect(DB::table('institution_memberships')->where('user_id', $account->id)->value('status'))->toBe('active')
        ->and($account->fresh()->getRoleNames()->sort()->values()->all())->toBe(['Docente', 'Representante'])
        ->and(InvitationModel::query()->sole()->status)->toBe('accepted');
});

test('the new member can then log in to the institution', function () {
    $institution = tenant();
    accept($institution, invitationToken($institution, 'ana@example.com'));

    $this->get(centralUrl("/invitaciones/{$institution->subdomain}/aceptada"))
        ->assertInertia(fn ($page) => $page
            ->component('Auth/InvitationAccepted')
            ->where('loginUrl', tenantUrl($institution, '/login'))
        );

    $this->post(tenantUrl($institution, '/login'), ['email' => 'ana@example.com', 'password' => 'mi-contrasena-nueva'])
        ->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('an invitation is still valid days later, unlike a password reset link', function () {
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com');

    $this->travel(6)->days();

    accept($institution, $token)->assertSessionHasNoErrors();
});

test('an invitation expires after a week', function () {
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com');

    $this->travel(8)->days();

    accept($institution, $token)->assertSessionHasErrors(['email' => 'Esta invitación no es válida o ya caducó.']);
    expect(User::query()->where('email', 'ana@example.com')->exists())->toBeFalse();
});

test('an invalid invitation token is rejected', function () {
    $institution = tenant();
    invitationToken($institution, 'ana@example.com');

    accept($institution, 'not-the-token')->assertSessionHasErrors(['email' => 'Esta invitación no es válida o ya caducó.']);
    expect(User::query()->where('email', 'ana@example.com')->exists())->toBeFalse();
});

test('an invitation can only be used once', function () {
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com');
    accept($institution, $token)->assertSessionHasNoErrors();

    accept($institution, $token)->assertSessionHasErrors(['email' => 'Esta invitación no es válida o ya caducó.']);
});

test('a cancelled invitation can not be used', function () {
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com');
    useTenant($institution);
    InvitationModel::query()->sole()->forceFill(['status' => 'cancelled'])->save();
    leaveTenant();

    accept($institution, $token)->assertSessionHasErrors(['email' => 'Esta invitación no es válida o ya caducó.']);
});

test('an invitation is only valid for the institution that sent it', function () {
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com');
    $other = tenant();
    leaveTenant();

    accept($other, $token)->assertSessionHasErrors(['email' => 'Esta invitación no es válida o ya caducó.']);
});

test('an email that already has an account can not accept a link yet', function () {
    $elsewhere = tenant();
    User::factory()->for($elsewhere)->create(['email' => 'ana@example.com', 'password' => 'la-de-siempre']);
    $institution = tenant();
    $token = invitationToken($institution, 'ana@example.com');

    accept($institution, $token)->assertSessionHasErrors('email');

    $account = User::query()->where('email', 'ana@example.com')->sole();
    expect(Hash::check('la-de-siempre', $account->password))->toBeTrue();

    useTenant($institution);
    expect(DB::table('institution_memberships')->where('user_id', $account->id)->exists())->toBeFalse();
});

test('invitation links sent before SPEC-004 still work', function () {
    $institution = tenant();
    invitationToken($institution, 'ana@example.com');
    useTenant($institution);
    // The password broker kept a bcrypt hash of the token (IDG-R09.6).
    InvitationModel::query()->sole()->forceFill(['token_hash' => Hash::make('legacy-token')])->save();
    leaveTenant();

    $this->get(tenantUrl($institution, '/invitation/legacy-token?email=ana@example.com'))
        ->assertRedirect(centralUrl("/invitaciones/{$institution->subdomain}/legacy-token?email=ana%40example.com"));

    leaveTenant();
    accept($institution, 'legacy-token')->assertSessionHasNoErrors();
});
