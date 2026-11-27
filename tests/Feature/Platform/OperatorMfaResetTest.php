<?php

use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Infrastructure\Notifications\OperatorLinkMail;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R03.7, R01.10 over HTTP, with V4: after a reset of its
 * second factor, an operator's only way back is the link it receives.
 */
beforeEach(function () {
    Notification::fake();
    leaveTenant();
    $this->support = storedOperator('soporte@aulax.ve', OperatorRole::Support);
    $this->boss = signedInOperator();
});

test('the reset ends the operator sessions and leaves the link as its only way in', function () {
    $this->post(adminUrl("/operadores/{$this->support->value}/restablecer-mfa"))->assertRedirect();

    $token = null;
    Notification::assertSentOnDemand(OperatorLinkMail::class, function (OperatorLinkMail $mail) use (&$token) {
        $token = $mail->token;

        return $mail->purpose === InvitationPurpose::MfaReset;
    });

    $this->post(adminUrl('/logout'));

    // Its password alone no longer opens the panel, whatever the code.
    panelLogIn('soporte@aulax.ve', 'clave-de-operador', '000000')->assertSessionHasErrors('email');
    panelLogIn('soporte@aulax.ve', 'clave-de-operador', 'recup-1')->assertSessionHasErrors('email');

    $this->get(adminUrl("/invitacion/{$token}"))->assertOk()->assertInertia(fn ($page) => $page->where('enrolment.needsPassword', false));
    expect(app(OperatorRepository::class)->byId($this->support)->canLogIn())->toBeFalse();
});

test('an operator regenerates its own recovery codes, which voids the old ones', function () {
    $this->post(adminUrl('/codigos-de-recuperacion'))->assertRedirect(adminUrl('/codigos-de-recuperacion'));
    $this->get(adminUrl('/codigos-de-recuperacion'))->assertInertia(fn ($page) => $page->has('codes', 10));

    expect(app(OperatorRepository::class)->recoveryCodes($this->boss)->accepts('recup-1'))->toBeFalse();
});
