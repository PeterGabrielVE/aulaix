<?php

use AulaX\IdentityAccess\Infrastructure\Notifications\EmailAlreadyInUseNotice;
use AulaX\IdentityAccess\Infrastructure\Notifications\EmailChangedNotice;
use AulaX\IdentityAccess\Infrastructure\Notifications\EmailChangeVerification;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * SPEC-004 IDG-R05.1 – R05.4: the owner manages their identity, on the
 * central domain (IDG-R03.1).
 */
function owner(array $attributes = []): User
{
    $user = userWithRole(tenant(), 'Docente', ['name' => 'Ana Pérez', 'email' => 'ana@example.com', ...$attributes]);
    leaveTenant();
    test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    return $user;
}

function requestEmailChange(string $email): string
{
    Notification::fake();
    test()->post(centralUrl('/cuenta/correo'), ['email' => $email])->assertSessionHasNoErrors()->assertRedirect();

    $url = null;
    Notification::assertSentOnDemand(EmailChangeVerification::class, function (EmailChangeVerification $mail, array $channels, object $notifiable) use (&$url, $email) {
        $url = $mail->url;

        return $notifiable->routes['mail'] === $email;
    });

    return $url;
}

test('the account page is on the central domain', function () {
    owner();

    $this->get(centralUrl('/cuenta'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Central/Account')
            ->where('account.name', 'Ana Pérez')
            ->where('account.email', 'ana@example.com')
        );
});

test('an institution\'s profile only links to the account', function () {
    $institution = tenant();
    $user = userWithRole($institution, 'Docente');

    $this->actingAs($user)->get(tenantUrl($institution, '/profile'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('accountUrl', centralUrl('/cuenta')));
    $this->patch(tenantUrl($institution, '/profile'), ['name' => 'Otro', 'email' => 'otro@example.com'])->assertMethodNotAllowed();
});

test('the owner can change their name', function () {
    $user = owner();

    $this->patch(centralUrl('/cuenta/nombre'), ['name' => 'Ana María Pérez'])->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Ana María Pérez')
        ->and(DB::table('platform_audit_entries')->where('event_type', 'identity.account.name_changed')->value('subject_id'))->toBe((string) $user->id);
});

test('a new email is only used once verified at that address, and the old one is told', function () {
    $user = owner();

    $link = requestEmailChange('nueva@example.com');

    expect($user->fresh()->email)->toBe('ana@example.com');

    auth()->forgetGuards();
    $this->get($link)->assertRedirect(centralUrl('/cuenta'));

    expect($user->fresh()->email)->toBe('nueva@example.com');
    Notification::assertSentOnDemand(EmailChangedNotice::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'ana@example.com');
    expect(DB::table('platform_audit_entries')->where('event_type', 'identity.account.email_changed')->exists())->toBeTrue();
});

test('changing the email asks for the password again', function () {
    owner();
    $this->withSession(['auth.password_confirmed_at' => time() - 601]);

    $this->post(centralUrl('/cuenta/correo'), ['email' => 'nueva@example.com'])->assertRedirect(centralUrl('/confirmar-contrasena'));
});

test('an email already in use is refused without telling why', function () {
    userWithRole(tenant(), 'Docente', ['email' => 'ocupado@example.com']);
    $user = owner();
    Notification::fake();

    $this->post(centralUrl('/cuenta/correo'), ['email' => 'ocupado@example.com'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'Te enviamos un enlace para confirmar el nuevo correo.');

    Notification::assertSentOnDemandTimes(EmailChangeVerification::class, 0);
    Notification::assertSentOnDemand(EmailAlreadyInUseNotice::class, fn ($mail, $channels, $notifiable) => $notifiable->routes['mail'] === 'ocupado@example.com');
    expect($user->fresh()->pending_email)->toBeNull();
});

test('an email verification link expires after a day', function () {
    $user = owner();
    $link = requestEmailChange('nueva@example.com');

    $this->travel(25)->hours();

    $this->get($link)->assertSessionHasErrors('email');
    expect($user->fresh()->email)->toBe('ana@example.com');
});
