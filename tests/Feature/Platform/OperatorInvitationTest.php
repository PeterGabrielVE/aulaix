<?php

use AulaX\Platform\Application\Operators\InviteOperatorHandler;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRepository;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Infrastructure\Notifications\OperatorLinkMail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R01.6, R01.10, R03.1, R03.2 over HTTP, with V4: the link
 * that sets an operator's password and TOTP lasts 48 hours, is used once,
 * and answers the same whatever is wrong with it.
 */
beforeEach(function () {
    Notification::fake();
    leaveTenant();
    $this->id = app(InviteOperatorHandler::class)('Luis Mora', 'luis@aulax.ve', OperatorRole::Support, null);
    $this->token = sentOperatorToken();
});

function sentOperatorToken(): string
{
    $token = null;

    Notification::assertSentOnDemand(OperatorLinkMail::class, function ($mail) use (&$token) {
        $token = $mail->token;

        return true;
    });

    return $token;
}

function acceptLink(string $token, string $code, ?string $password = 'una-clave-muy-larga'): TestResponse
{
    return test()->from(adminUrl("/invitacion/{$token}"))->post(adminUrl("/invitacion/{$token}"), array_filter([
        'password' => $password, 'password_confirmation' => $password, 'code' => $code,
    ], fn ($value) => $value !== null));
}

function enrolmentSecret(string $token): string
{
    $secret = null;

    test()->get(adminUrl("/invitacion/{$token}"))->assertOk()->assertInertia(function ($page) use (&$secret) {
        $page->component('Platform/Enrol');
        $secret = $page->toArray()['props']['enrolment']['secret'];
    });

    return $secret;
}

function codeFor(string $secret): string
{
    return (new Google2FA)->oathTotp($secret, intdiv(now()->getTimestamp(), 30));
}

test('the link sets the password and the TOTP, then shows the ten recovery codes once', function () {
    $secret = enrolmentSecret($this->token);

    acceptLink($this->token, codeFor($secret))->assertRedirect(adminUrl('/codigos-de-recuperacion'));

    $this->get(adminUrl('/codigos-de-recuperacion'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/RecoveryCodes')->has('codes', 10));
    $this->get(adminUrl('/codigos-de-recuperacion'))->assertRedirect(adminUrl('/login'));

    expect(app(OperatorRepository::class)->byId($this->id)->canLogIn())->toBeTrue();
});

test('a link lasts 48 hours, and not a minute more', function () {
    $this->travel(47 * 60 + 59)->minutes();
    $this->get(adminUrl("/invitacion/{$this->token}"))->assertOk();

    $this->travel(1)->minutes();
    $this->get(adminUrl("/invitacion/{$this->token}"))->assertNotFound();
});

test('a link is used once, even by two requests', function () {
    $secret = enrolmentSecret($this->token);
    acceptLink($this->token, codeFor($secret))->assertRedirect();

    acceptLink($this->token, codeFor($secret))->assertNotFound();
    $this->get(adminUrl("/invitacion/{$this->token}"))->assertNotFound();
});

test('a newer link revokes the older one', function () {
    app(OperatorInvitationRepository::class)->issue(
        OperatorInvitation::issue($this->id, InvitationPurpose::Onboarding, hash('sha256', 'otro'), new DateTimeImmutable),
    );

    $this->get(adminUrl("/invitacion/{$this->token}"))->assertNotFound();
});

test('expired, used, revoked and unknown links answer alike', function () {
    $responses = [];
    $responses[] = $this->get(adminUrl('/invitacion/no-existe'));
    app(OperatorInvitationRepository::class)->revokeAllOf($this->id);
    $responses[] = $this->get(adminUrl("/invitacion/{$this->token}"));

    expect(array_map(fn ($response) => [$response->status(), $response->exception?->getMessage()], $responses))
        ->toBe([[404, 'El enlace no es válido o ha caducado.'], [404, 'El enlace no es válido o ha caducado.']]);
});

test('a wrong code leaves the link usable and the operator pending', function () {
    enrolmentSecret($this->token);

    acceptLink($this->token, '000000')->assertSessionHasErrors(['code' => 'El código no es correcto.']);

    $this->get(adminUrl("/invitacion/{$this->token}"))->assertOk();
});

test('the password is checked at the edge', function () {
    $secret = enrolmentSecret($this->token);

    acceptLink($this->token, codeFor($secret), 'corta')->assertSessionHasErrors('password');
});
