<?php

use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Infrastructure\Notifications\OperatorLinkMail;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R03 over HTTP, with PLT-NF04, NF05 and V4: the operators
 * screen, only for a SuperAdmin, with a fresh TOTP for every change.
 */
beforeEach(function () {
    Notification::fake();
    leaveTenant();
    $this->support = storedOperator('soporte@aulax.ve', OperatorRole::Support);
    $this->boss = signedInOperator();
});

test('a SuperAdmin lists the operators and invites one', function () {
    $this->get(adminUrl('/operadores'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Platform/Operators')
        ->has('operators', 2)
        ->missing('operators.0.totpSecret')
    );

    $this->from(adminUrl('/operadores'))->post(adminUrl('/operadores'), ['name' => 'Luis Mora', 'email' => 'luis@aulax.ve', 'role' => 'support'])
        ->assertRedirect(adminUrl('/operadores'))
        ->assertSessionHasNoErrors();

    expect(app(OperatorRepository::class)->byEmail(OperatorEmail::fromString('luis@aulax.ve'))->status)->toBe(OperatorStatus::Pending);
    Notification::assertSentOnDemand(OperatorLinkMail::class);
});

test('a role change takes effect and closes that operator sessions', function () {
    $version = app(OperatorRepository::class)->byId($this->support)->sessionVersion;

    $this->put(adminUrl("/operadores/{$this->support->value}/nivel"), ['role' => 'super_admin'])->assertRedirect();

    $operator = app(OperatorRepository::class)->byId($this->support);

    expect($operator->role)->toBe(OperatorRole::SuperAdmin)
        ->and($operator->sessionVersion)->toBe($version + 1);
});

test('deactivating an operator revokes its pending links (V4)', function () {
    app(OperatorInvitationRepository::class)->issue(
        OperatorInvitation::issue($this->support, InvitationPurpose::MfaReset, hash('sha256', 'pendiente'), new DateTimeImmutable),
    );

    $this->put(adminUrl("/operadores/{$this->support->value}/estado"), ['active' => false])->assertRedirect();

    expect(app(OperatorRepository::class)->byId($this->support)->status)->toBe(OperatorStatus::Inactive);
    $this->get(adminUrl('/invitacion/pendiente'))->assertNotFound();
});

test('the last active SuperAdmin can not be demoted, nor demote itself', function () {
    $this->from(adminUrl('/operadores'))->put(adminUrl("/operadores/{$this->boss->value}/nivel"), ['role' => 'support'])
        ->assertSessionHasErrors('operator');

    expect(app(OperatorRepository::class)->byId($this->boss)->role)->toBe(OperatorRole::SuperAdmin);
});

test('a Support gets 403 on every operator route', function (string $method, Closure $path) {
    $this->post(adminUrl('/logout'));
    panelLogIn('soporte@aulax.ve', 'clave-de-operador', currentCode($this->support));

    $this->{$method}(adminUrl($path->call($this)), ['role' => 'support', 'active' => false, 'name' => 'X', 'email' => 'x@aulax.ve'])
        ->assertForbidden();
})->with([
    'list' => ['get', fn () => '/operadores'],
    'invite' => ['post', fn () => '/operadores'],
    'change a role' => ['put', fn () => "/operadores/{$this->boss->value}/nivel"],
    'deactivate' => ['put', fn () => "/operadores/{$this->boss->value}/estado"],
    'reset the second factor' => ['post', fn () => "/operadores/{$this->boss->value}/restablecer-mfa"],
]);
