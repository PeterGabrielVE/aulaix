<?php

use AulaX\Platform\Domain\OperatorRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-014 PLT-R01 over HTTP, with V5: the panel lives at admin.APP_DOMAIN
 * with its own login and session; every failed login looks the same.
 */
beforeEach(function () {
    leaveTenant();
    $this->operator = storedOperator();
});

function panelLogIn(string $email, string $password, string $code): TestResponse
{
    return test()->from(adminUrl('/login'))->post(adminUrl('/login'), ['email' => $email, 'password' => $password, 'code' => $code]);
}

test('an operator enters with email, password and TOTP, and only the admin host gets the cookie', function () {
    panelLogIn('ana@aulax.ve', 'clave-de-operador', currentCode($this->operator))
        ->assertRedirect(route('platform.home'));

    $this->get(adminUrl('/'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Platform/Home')
        ->where('platform.operator.name', 'Ana Rivas')
        ->where('platform.operator.role', 'super_admin')
    );

    expect(config('session.domain'))->toBeNull()
        ->and(DB::table('platform_audit_entries')->where('event_type', 'platform.login.succeeded')->value('operator'))->toBe("operador #{$this->operator->value}");
});

test('every failure gives the same answer and the same entry (V5)', function (Closure $prepare, string $email, string $password, Closure $code) {
    $prepare->call($this);

    panelLogIn($email, $password, $code->call($this))
        ->assertRedirect(adminUrl('/login'))
        ->assertSessionHasErrors(['email' => 'Datos de acceso incorrectos.']);

    $this->assertGuest('platform');

    $entry = DB::table('platform_audit_entries')->where('event_type', 'platform.login.failed')->sole();

    expect($entry->subject_type.'|'.$entry->subject_id.'|'.$entry->changes)->toBe('platform_login|panel|{}');
})->with([
    'unknown email' => [fn () => null, 'nadie@aulax.ve', 'clave-de-operador', fn () => currentCode($this->operator)],
    'wrong password' => [fn () => null, 'ana@aulax.ve', 'otra-clave', fn () => currentCode($this->operator)],
    'wrong code' => [fn () => null, 'ana@aulax.ve', 'clave-de-operador', fn () => '000000'],
    'code already used' => [function () {
        $operators = app(OperatorRepository::class);
        $operator = $operators->byId($this->operator);
        $operator->useTotpStep(intdiv(now()->getTimestamp(), 30) + 1);
        $operators->save($operator);
    }, 'ana@aulax.ve', 'clave-de-operador', fn () => currentCode($this->operator)],
    'recovery code spent' => [function () {
        $operators = app(OperatorRepository::class);
        $operators->saveRecoveryCodes($this->operator, $operators->recoveryCodes($this->operator)->use('recup-1'));
    }, 'ana@aulax.ve', 'clave-de-operador', fn () => 'recup-1'],
    'operator deactivated' => [function () {
        $operators = app(OperatorRepository::class);
        $operator = $operators->byId($this->operator);
        $operator->deactivate();
        $operators->save($operator);
    }, 'ana@aulax.ve', 'clave-de-operador', fn () => currentCode($this->operator)],
    'an account of an institution' => [function () {
        $institution = tenant();
        userWithRole($institution, 'Administrador', ['email' => 'director@colegio.ve']);
        leaveTenant();
    }, 'director@colegio.ve', 'password', fn () => '123456'],
]);

test('a recovery code opens the panel once', function () {
    panelLogIn('ana@aulax.ve', 'clave-de-operador', 'recup-2')->assertRedirect(route('platform.home'));

    expect(app(OperatorRepository::class)->recoveryCodes($this->operator)->remaining())->toBe(9);
});

test('the sixth attempt in a minute is refused, whatever failed', function () {
    foreach (range(1, 5) as $attempt) {
        panelLogIn('ana@aulax.ve', 'otra-clave', '000000')->assertSessionHasErrors('email');
    }

    panelLogIn('ana@aulax.ve', 'clave-de-operador', currentCode($this->operator))->assertTooManyRequests();
});

test('the panel needs an operator session, and an account of an institution is none', function () {
    $this->get(adminUrl('/'))->assertRedirect(adminUrl('/login'));

    $institution = tenant();
    $user = userWithRole($institution, 'Administrador');
    leaveTenant();

    $this->actingAs($user)->get(adminUrl('/'))->assertRedirect(adminUrl('/login'));
});

test('an operator email does not open the central login', function () {
    $this->post(centralUrl('/login'), ['email' => 'ana@aulax.ve', 'password' => 'clave-de-operador'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('web');
});

test('the session ends after 30 minutes without activity', function () {
    panelLogIn('ana@aulax.ve', 'clave-de-operador', currentCode($this->operator));
    $this->get(adminUrl('/'))->assertOk();

    $this->travel(31)->minutes();

    $this->get(adminUrl('/'))->assertRedirect(adminUrl('/login'));
    $this->assertGuest('platform');
});

test('logging out ends the session on the server', function () {
    panelLogIn('ana@aulax.ve', 'clave-de-operador', currentCode($this->operator));

    $this->post(adminUrl('/logout'))->assertRedirect(adminUrl('/login'));

    $this->assertGuest('platform');
    $this->get(adminUrl('/'))->assertRedirect(adminUrl('/login'));
});

test('a session started before a deactivation ends on its next request', function () {
    panelLogIn('ana@aulax.ve', 'clave-de-operador', currentCode($this->operator));
    $operators = app(OperatorRepository::class);
    $operator = $operators->byId($this->operator);
    $operator->deactivate();
    $operators->save($operator);

    $this->get(adminUrl('/'))->assertRedirect(adminUrl('/login'));
});
