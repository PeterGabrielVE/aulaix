<?php

use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Infrastructure\Notifications\OperatorBlockedMail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-017 RSP-R03.1 – R03.11, NF05: per-operator lockout across IPs after 10
 * consecutive code failures, confirmation rate limiting, session invalidation,
 * notifications, audit log, 15-minute expiry, and lifting only via MFA reset.
 */
beforeEach(function () {
    leaveTenant();
    $this->boss = storedOperator('jefa@aulax.ve', OperatorRole::SuperAdmin);
    $this->support = storedOperator('soporte@aulax.ve', OperatorRole::Support);
});

test('blocks an operator for 15 minutes after 10 failed codes across distinct IPs, audits, emails and closes open sessions', function () {
    Notification::fake();

    // Support starts with an open session first.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
        ->from(adminUrl('/login'))
        ->post(adminUrl('/login'), [
            'email' => 'soporte@aulax.ve',
            'password' => 'clave-de-operador',
            'code' => currentCode($this->support),
        ])->assertRedirect(route('platform.home'));

    // Ten failed code attempts from 10 different IPs (bypassing per-IP rate limits).
    for ($i = 1; $i <= 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->post(adminUrl('/confirmar-codigo'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'El código no es correcto.']);
    }

    // Open session was closed immediately (RSP-R03.5).
    $this->assertGuest('platform');
    $this->get(adminUrl('/'))->assertRedirect(adminUrl('/login'));

    // Audit entry recorded (RSP-R03.7).
    expect(DB::table('platform_audit_entries')->where('event_type', 'platform.operator.blocked')->where('subject_id', (string) $this->support->value)->exists())
        ->toBeTrue();

    // Operator and active SuperAdmins notified with IPs and timestamp (RSP-R03.6).
    Notification::assertSentTo(new AnonymousNotifiable, OperatorBlockedMail::class, function (OperatorBlockedMail $mail, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routes['mail'] === 'soporte@aulax.ve' && $mail->forSelf && count($mail->failures) === 10;
    });
    Notification::assertSentTo(new AnonymousNotifiable, OperatorBlockedMail::class, function (OperatorBlockedMail $mail, array $channels, AnonymousNotifiable $notifiable) {
        return $notifiable->routes['mail'] === 'jefa@aulax.ve' && ! $mail->forSelf && count($mail->failures) === 10;
    });

    // While blocked, even a valid TOTP is rejected with the single generic message (RSP-R03.4, NF05).
    $this->travel(35)->seconds();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.50'])
        ->from(adminUrl('/login'))
        ->post(adminUrl('/login'), [
            'email' => 'soporte@aulax.ve',
            'password' => 'clave-de-operador',
            'code' => currentCode($this->support),
        ])
        ->assertRedirect(adminUrl('/login'))
        ->assertSessionHasErrors(['email' => 'Datos de acceso incorrectos.']);

    // Once 15 minutes pass, valid login works again and resets failures (RSP-R03.9).
    $this->travel(15)->minutes();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.51'])
        ->from(adminUrl('/login'))
        ->post(adminUrl('/login'), [
            'email' => 'soporte@aulax.ve',
            'password' => 'clave-de-operador',
            'code' => currentCode($this->support),
        ])
        ->assertRedirect(route('platform.home'));

    expect(app(OperatorRepository::class)->byId($this->support)->codeFailures)->toBe([]);
});

test('wrong passwords never block an operator and produce the same answer', function () {
    Notification::fake();

    for ($i = 1; $i <= 12; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->from(adminUrl('/login'))
            ->post(adminUrl('/login'), [
                'email' => 'soporte@aulax.ve',
                'password' => 'clave-incorrecta',
                'code' => '000000',
            ])
            ->assertRedirect(adminUrl('/login'))
            ->assertSessionHasErrors(['email' => 'Datos de acceso incorrectos.']);
    }

    expect(app(OperatorRepository::class)->byId($this->support)->codeFailures)->toBe([])
        ->and(app(OperatorRepository::class)->byId($this->support)->blockedUntil)->toBeNull();

    Notification::assertNothingSent();

    // Valid login works right away.
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.88'])
        ->from(adminUrl('/login'))
        ->post(adminUrl('/login'), [
            'email' => 'soporte@aulax.ve',
            'password' => 'clave-de-operador',
            'code' => currentCode($this->support),
        ])
        ->assertRedirect(route('platform.home'));
});

test('same answer for blocked operator and unknown email', function () {
    for ($i = 1; $i <= 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->from(adminUrl('/login'))
            ->post(adminUrl('/login'), [
                'email' => 'soporte@aulax.ve',
                'password' => 'clave-de-operador',
                'code' => '000000',
            ]);
    }

    $blocked = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->from(adminUrl('/login'))
        ->post(adminUrl('/login'), [
            'email' => 'soporte@aulax.ve',
            'password' => 'clave-de-operador',
            'code' => currentCode($this->support),
        ]);

    $unknown = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.21'])
        ->from(adminUrl('/login'))
        ->post(adminUrl('/login'), [
            'email' => 'nadie@aulax.ve',
            'password' => 'clave-de-operador',
            'code' => '123456',
        ]);

    expect($blocked->getStatusCode())->toBe($unknown->getStatusCode())
        ->and($blocked->headers->get('Location'))->toBe($unknown->headers->get('Location'));
    $blocked->assertSessionHasErrors(['email' => 'Datos de acceso incorrectos.']);
    $unknown->assertSessionHasErrors(['email' => 'Datos de acceso incorrectos.']);
});

test('limits TOTP confirmation to 5 attempts per minute per operator and IP', function () {
    panelLogIn('soporte@aulax.ve', 'clave-de-operador', currentCode($this->support))->assertRedirect(route('platform.home'));

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->post(adminUrl('/confirmar-codigo'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->post(adminUrl('/confirmar-codigo'), ['code' => '000000'])
        ->assertTooManyRequests();

    // Another operator on the same IP is not throttled by the first operator's attempts.
    $this->post(adminUrl('/logout'));
    panelLogIn('jefa@aulax.ve', 'clave-de-operador', currentCode($this->boss))->assertRedirect(route('platform.home'));

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
        ->post(adminUrl('/confirmar-codigo'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
});

test('resetting MFA of a blocked operator lifts the lockout and clears failures', function () {
    Notification::fake();

    for ($i = 1; $i <= 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->from(adminUrl('/login'))
            ->post(adminUrl('/login'), [
                'email' => 'soporte@aulax.ve',
                'password' => 'clave-de-operador',
                'code' => '000000',
            ]);
    }

    expect(app(OperatorRepository::class)->byId($this->support)->blockedUntil)->not->toBeNull();

    // SuperAdmin resets the blocked operator's MFA (RSP-R03.11).
    panelLogIn('jefa@aulax.ve', 'clave-de-operador', currentCode($this->boss))->assertRedirect(route('platform.home'));
    $this->post(adminUrl("/operadores/{$this->support->value}/restablecer-mfa"))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $reset = app(OperatorRepository::class)->byId($this->support);
    expect($reset->blockedUntil)->toBeNull()
        ->and($reset->codeFailures)->toBe([])
        ->and($reset->totpSecret)->toBeNull();
});
