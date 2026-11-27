<?php

use App\Console\Commands\ResolveConsolidationCase;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require_once dirname(__DIR__, 2).'/Fixtures/Platform/platform_helpers.php';

/**
 * SPEC-017 RSP-R06.1 – R06.8, R07.2, R07.3: interactive TOTP authentication,
 * single-use codes across panel and console, recovery code rejection, lockout
 * accumulation, non-interactive refusal, and explicit confirmation before applying.
 */
beforeEach(function () {
    Notification::fake();
    $this->older = userWithRole(tenant(['name' => 'Colegio Norte']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana Pérez', 'consolidation_state' => 'review']);
    $this->newer = userWithRole(tenant(['name' => 'Colegio Sur']), 'Docente', ['email' => 'ana@example.com', 'name' => 'Ana María Pérez', 'consolidation_state' => 'review']);
    leaveTenant();
    $this->luis = storedOperator('luis@aulax.ve', OperatorRole::Support);
});

function consoleResolveArgs(array $overrides = []): array
{
    $test = test();

    return [
        'accounts' => [$test->older->id, $test->newer->id],
        '--operator' => 'luis@aulax.ve',
        '--decision' => 'consolidate',
        '--reason' => 'Cédula verificada',
        '--verification-ref' => 'TICKET-7',
        ...$overrides,
    ];
}

test('command signature has no option or argument for the TOTP code (RSP-R06.3)', function () {
    $definition = (new ResolveConsolidationCase)->getDefinition();

    expect($definition->hasOption('totp'))->toBeFalse()
        ->and($definition->hasOption('code'))->toBeFalse()
        ->and($definition->hasArgument('totp'))->toBeFalse()
        ->and($definition->hasArgument('code'))->toBeFalse();
});

test('non-interactive execution is refused without changes and audited (RSP-R06.4)', function () {
    $this->artisan('identity:consolidation:resolve', [...consoleResolveArgs(), '--no-interaction' => true])
        ->assertFailed();

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2)
        ->and(DB::table('platform_audit_entries')->where('event_type', 'platform.console_auth.refused')->where('origin', 'console')->exists())->toBeTrue()
        ->and(app(OperatorRepository::class)->byId($this->luis)->codeFailures)->toBe([]);
});

test('wrong TOTP or recovery code is refused, audited and counted as a code failure (RSP-R06.2, R06.5, R06.7)', function () {
    $this->artisan('identity:consolidation:resolve', consoleResolveArgs())
        ->expectsQuestion('Código TOTP del operador', '000000')
        ->assertFailed();

    $this->artisan('identity:consolidation:resolve', consoleResolveArgs())
        ->expectsQuestion('Código TOTP del operador', 'recup-1')
        ->assertFailed();

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2)
        ->and(app(OperatorRepository::class)->byId($this->luis)->codeFailures)->toHaveCount(2)
        ->and(app(OperatorRepository::class)->recoveryCodes($this->luis)->remaining())->toBe(10)
        ->and(DB::table('platform_audit_entries')->where('event_type', 'platform.console_auth.refused')->count())->toBe(2);
});

test('a TOTP used in the panel cannot be reused in the console and vice versa (RSP-R06.6)', function () {
    $code = currentCode($this->luis);
    panelLogIn('luis@aulax.ve', 'clave-de-operador', $code)->assertRedirect(route('platform.home'));

    $this->artisan('identity:consolidation:resolve', consoleResolveArgs())
        ->expectsQuestion('Código TOTP del operador', $code)
        ->assertFailed();

    // Advance one TOTP window, use in console, then verify panel rejects the same code.
    $this->travel(35)->seconds();
    $consoleCode = currentCode($this->luis);
    $this->artisan('identity:consolidation:resolve', [...consoleResolveArgs(), '--dry-run' => true])
        ->expectsQuestion('Código TOTP del operador', $consoleCode)
        ->assertSuccessful();

    $this->post(adminUrl('/logout'));
    panelLogIn('luis@aulax.ve', 'clave-de-operador', $consoleCode)->assertSessionHasErrors('email');
});

test('declining confirmation after preview leaves the accounts untouched and audits the preview (RSP-R06.8, R07.2, R07.3)', function () {
    $this->artisan('identity:consolidation:resolve', consoleResolveArgs())
        ->expectsQuestion('Código TOTP del operador', currentCode($this->luis))
        ->expectsConfirmation('¿Confirmas aplicar estos cambios?', 'no')
        ->expectsOutputToContain('Cancelado: no se aplicó ningún cambio.')
        ->assertSuccessful();

    $preview = DB::table('platform_audit_entries')->where('event_type', 'identity.consolidation.previewed')->sole();

    expect(User::query()->where('email', 'ana@example.com')->count())->toBe(2)
        ->and($preview->origin)->toBe('console')
        ->and($preview->operator)->toBe('luis@aulax.ve')
        ->and($preview->changes)->not->toContain('ana@example.com')
        ->and($preview->changes)->not->toContain('Ana Pérez');
});

test('inactive or blocked operator is refused in console and audited (RSP-R06.2)', function () {
    $repository = app(OperatorRepository::class);
    app(OperatorLock::class)->lockOperator($this->luis);
    $operator = $repository->byId($this->luis);
    $operator->deactivate();
    $repository->save($operator);

    $this->artisan('identity:consolidation:resolve', consoleResolveArgs())
        ->expectsQuestion('Código TOTP del operador', currentCode($this->luis))
        ->assertFailed();

    expect(DB::table('platform_audit_entries')->where('event_type', 'platform.console_auth.refused')->exists())->toBeTrue();
});
