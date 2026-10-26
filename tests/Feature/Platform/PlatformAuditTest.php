<?php

use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Infrastructure\Notifications\OperatorLinkMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R07.1, R07.3: every access, action and query of the panel
 * goes to the platform log, with its operator; no password, TOTP secret,
 * recovery code or token ever does.
 */
beforeEach(function () {
    Notification::fake();
    Storage::fake(config('tenancy.files_disk'));
    $this->institution = tenant(['name' => 'Colegio Norte']);
    userWithRole($this->institution, 'Administrador', ['email' => 'rosa@norte.ve']);
    $this->reviewed = userWithRole($this->institution, 'Docente', ['email' => 'ana@example.com', 'consolidation_state' => 'review']);
    userWithRole(tenant(), 'Docente', ['email' => 'ana@example.com', 'consolidation_state' => 'review']);
    leaveTenant();
    $this->boss = signedInOperator();
});

test('every access, action and query is recorded, and no secret is', function () {
    $secrets = ['clave-de-operador', 'una-clave-muy-larga', app(OperatorRepository::class)->byId($this->boss)->totpSecret];
    $secrets = [...$secrets, ...array_map(fn (int $n) => "recup-{$n}", range(1, 10))];

    // A SuperAdmin invites an operator, who joins with a password and a TOTP.
    $this->post(adminUrl('/operadores'), ['name' => 'Luis Mora', 'email' => 'luis@aulax.ve', 'role' => 'support'])->assertSessionHasNoErrors();
    $token = null;
    Notification::assertSentOnDemand(OperatorLinkMail::class, function (OperatorLinkMail $mail) use (&$token) {
        $token = $mail->token;

        return true;
    });
    $this->post(adminUrl('/logout'));

    $secret = null;
    $this->get(adminUrl("/invitacion/{$token}"))->assertInertia(function ($page) use (&$secret) {
        $secret = $page->toArray()['props']['enrolment']['secret'];
    });
    $this->post(adminUrl("/invitacion/{$token}"), [
        'password' => 'una-clave-muy-larga', 'password_confirmation' => 'una-clave-muy-larga',
        'code' => (new Google2FA)->oathTotp($secret, intdiv(now()->getTimestamp(), 30)),
    ])->assertRedirect(adminUrl('/codigos-de-recuperacion'));
    $this->get(adminUrl('/codigos-de-recuperacion'))->assertInertia(function ($page) use (&$secrets) {
        $secrets = [...$secrets, ...$page->toArray()['props']['codes']];
    });
    $luis = app(OperatorRepository::class)->byEmail(OperatorEmail::fromString('luis@aulax.ve'))->id;
    array_push($secrets, $token, $secret);

    // A failed login, then the SuperAdmin again, a TOTP step later.
    panelLogIn('ana@aulax.ve', 'otra-clave', '000000');
    $this->travel(31)->seconds();
    panelLogIn('ana@aulax.ve', 'clave-de-operador', currentCode($this->boss))->assertRedirect(route('platform.home'));

    // Actions.
    $this->put(adminUrl("/operadores/{$luis->value}/nivel"), ['role' => 'super_admin'])->assertSessionHasNoErrors();
    $this->post(adminUrl("/operadores/{$luis->value}/restablecer-mfa"))->assertSessionHasNoErrors();
    $regenerated = $this->post(adminUrl('/codigos-de-recuperacion'));
    $secrets = [...$secrets, ...$regenerated->getSession()->get('platform.recovery_codes')];
    $this->put(adminUrl("/planteles/{$this->institution->id}/estado"), ['active' => false, 'reason' => 'Falta de pago'])->assertSessionHasNoErrors();

    // Queries.
    $this->get(adminUrl("/planteles/{$this->institution->id}"))->assertOk();
    $this->get(adminUrl("/consolidaciones/{$this->reviewed->id}"))->assertOk();
    $this->get(adminUrl('/bitacora'))->assertOk();
    $this->post(adminUrl('/logout'));

    $types = DB::table('platform_audit_entries')->pluck('event_type')->all();
    expect($types)->toContain(
        'platform.operator.invited',
        'platform.operator.activated',
        'platform.login.failed',
        'platform.login.succeeded',
        'platform.operator.role_changed',
        'platform.operator.mfa_reset',
        'platform.operator.recovery_codes_regenerated',
        'platform.institution.deactivated',
        'platform.institution.viewed',
        'platform.consolidation_case.viewed',
        'platform.activity.viewed',
        'platform.session.ended',
    );

    $panelEntries = DB::table('platform_audit_entries')->where('event_type', 'not like', 'platform.login.failed')->where('origin', 'platform')->get();
    expect($panelEntries->pluck('operator')->filter()->unique()->values()->all())->toEqualCanonicalizing(["operador #{$this->boss->value}", "operador #{$luis->value}"])
        ->and($panelEntries->pluck('ip')->filter()->count())->toBe($panelEntries->count());

    $log = json_encode(DB::table('platform_audit_entries')->get()).json_encode(DB::table('audit_entries')->get());
    foreach (array_filter($secrets) as $value) {
        expect(str_contains($log, $value))->toBeFalse("The log holds a secret: {$value}");
    }
});
