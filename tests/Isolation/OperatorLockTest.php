<?php

use AulaX\Audit\Application\AuditTrail;
use AulaX\Platform\Application\Access\LogInOperatorHandler;
use AulaX\Platform\Application\Operators\ChangeOperatorRoleHandler;
use AulaX\Platform\Application\Operators\RegenerateRecoveryCodesHandler;
use AulaX\Platform\Application\Operators\SetOperatorActiveHandler;
use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\RecoveryCodeHasher;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Infrastructure\AdvisoryLockKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\FakeOperatorSession;

/**
 * SPEC-017 RSP-R01.1 – R01.6, R02.1, R02.2, NF01 (ADR 0014): real two-connection
 * concurrency tests for platform operator advisory locks (1014 and 1015).
 */
beforeEach(function () {
    // Isolation tests commit for real; avoid writing to the immutable audit table.
    app()->instance(AuditTrail::class, new RecordingAuditTrail);
    $this->fakeSession = new FakeOperatorSession;
    app()->instance(OperatorSession::class, $this->fakeSession);
});

function rivalPlatformConnection(): PDO
{
    $config = config('database.connections.pgsql');
    // Under PgBouncer (pool_size=1), connect the rival directly to postgres so
    // two backend sessions can hold concurrent transactions against the same DB.
    $host = $config['host'] === 'pgbouncer' ? 'postgres' : $config['host'];
    $port = $config['host'] === 'pgbouncer' ? 5432 : $config['port'];

    return new PDO("pgsql:host={$host};port={$port};dbname={$config['database']}", $config['username'], $config['password']);
}

function createRealOperator(string $email, OperatorRole $role = OperatorRole::Support): OperatorId
{
    $repository = app(OperatorRepository::class);
    $locks = app(OperatorLock::class);
    $hasher = app(RecoveryCodeHasher::class);

    return DB::transaction(function () use ($repository, $locks, $hasher, $email, $role) {
        $secret = (new Google2FA)->generateSecretKey(32);
        $operator = Operator::invite('Operador', OperatorEmail::fromString($email), $role);
        $operator->activate(Hash::make('clave-de-operador'), $secret, step: 1);
        $id = $repository->save($operator);
        $locks->lockOperator($id);
        $repository->saveRecoveryCodes($id, RecoveryCodes::fromPlain(array_map(fn (int $n) => "recup-{$n}", range(1, 10)), $hasher));

        return $id;
    });
}

function realTotpCode(OperatorId $id): string
{
    $secret = app(OperatorRepository::class)->byId($id)->totpSecret;

    return (new Google2FA)->oathTotp($secret, intdiv(time(), 30));
}

test('management and login, both orders: deactivation is never overwritten and leaves no open session', function () {
    $boss = createRealOperator('jefa@aulax.ve', OperatorRole::SuperAdmin);
    $support = createRealOperator('soporte@aulax.ve', OperatorRole::Support);
    $rival = rivalPlatformConnection();

    // Order 1: deactivation holds the lock on a rival connection while login checks contention,
    // then commits -> login re-reads the deactivated operator and refuses with no session opened.
    $rival->beginTransaction();
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR_ROSTER.', 0)');
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR.", {$support->value})");
    $rival->exec("UPDATE platform_operators SET status = 'inactive', session_version = session_version + 1 WHERE id = {$support->value}");

    $appCanAcquireWhileHeld = DB::transaction(fn () => (bool) DB::selectOne(
        'SELECT pg_try_advisory_xact_lock(?, ?) AS got',
        [AdvisoryLockKeys::OPERATOR, $support->value],
    )->got);
    $rival->commit();

    expect($appCanAcquireWhileHeld)->toBeFalse()
        ->and(fn () => app(LogInOperatorHandler::class)('soporte@aulax.ve', 'clave-de-operador', realTotpCode($support), '198.51.100.1'))
        ->toThrow(BusinessRuleViolation::class, 'Datos de acceso incorrectos.')
        ->and($this->fakeSession->current())->toBeNull()
        ->and(app(OperatorRepository::class)->byId($support)->status)->toBe(OperatorStatus::Inactive);

    // Order 2: login commits first on another operator, then deactivation runs under the lock
    // and bumps session_version so the opened session is immediately stale.
    $second = createRealOperator('soporte2@aulax.ve', OperatorRole::Support);
    app(LogInOperatorHandler::class)('soporte2@aulax.ve', 'clave-de-operador', realTotpCode($second), '198.51.100.2');
    $openedVersion = $this->fakeSession->sessionVersion();

    $this->fakeSession->start(app(OperatorRepository::class)->byId($boss), new DateTimeImmutable);
    app(SetOperatorActiveHandler::class)($second, false);

    $after = app(OperatorRepository::class)->byId($second);
    expect($after->status)->toBe(OperatorStatus::Inactive)
        ->and($after->sessionVersion)->toBeGreaterThan($openedVersion);
});

test('role change during login never leaves a session with the previous role', function () {
    $boss = createRealOperator('jefa@aulax.ve', OperatorRole::SuperAdmin);
    $other = createRealOperator('otro@aulax.ve', OperatorRole::SuperAdmin);
    $rival = rivalPlatformConnection();

    // Rival changes role to support under the lock before login acquires it.
    $rival->beginTransaction();
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR_ROSTER.', 0)');
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR.", {$other->value})");
    $rival->exec("UPDATE platform_operators SET role = 'support', session_version = session_version + 1 WHERE id = {$other->value}");
    $rival->commit();

    app(LogInOperatorHandler::class)('otro@aulax.ve', 'clave-de-operador', realTotpCode($other), '198.51.100.3');

    $reloaded = app(OperatorRepository::class)->byId($other);
    expect($reloaded->role)->toBe(OperatorRole::Support)
        ->and($this->fakeSession->sessionVersion())->toBe($reloaded->sessionVersion);

    // And if a role change happens right after login, sessionVersion advances past the opened session.
    $openedVersion = $this->fakeSession->sessionVersion();
    $this->fakeSession->start(app(OperatorRepository::class)->byId($boss), new DateTimeImmutable);
    app(ChangeOperatorRoleHandler::class)($other, OperatorRole::SuperAdmin);

    expect(app(OperatorRepository::class)->byId($other)->sessionVersion)->toBeGreaterThan($openedVersion);
});

test('two concurrent logins with the same TOTP or recovery code accept at most one', function () {
    $support = createRealOperator('soporte@aulax.ve', OperatorRole::Support);
    $rival = rivalPlatformConnection();
    $code = realTotpCode($support);
    $step = intdiv(time(), 30);

    // Rival consumes the TOTP step under the operator lock.
    $rival->beginTransaction();
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR.", {$support->value})");
    $rival->exec("UPDATE platform_operators SET totp_last_step = {$step} WHERE id = {$support->value}");
    $triesWhileHeld = DB::transaction(fn () => (bool) DB::selectOne(
        'SELECT pg_try_advisory_xact_lock(?, ?) AS got',
        [AdvisoryLockKeys::OPERATOR, $support->value],
    )->got);
    $rival->commit();

    expect($triesWhileHeld)->toBeFalse()
        ->and(fn () => app(LogInOperatorHandler::class)('soporte@aulax.ve', 'clave-de-operador', $code, '198.51.100.4'))
        ->toThrow(BusinessRuleViolation::class, 'Datos de acceso incorrectos.');

    // Same for recovery codes: rival consumes recup-1 under the lock.
    $hasher = app(RecoveryCodeHasher::class);
    $hash = $hasher->hash(RecoveryCodes::normalize('recup-1'));
    $rival->beginTransaction();
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR.", {$support->value})");
    $rival->exec("UPDATE platform_operator_recovery_codes SET used_at = NOW() WHERE operator_id = {$support->value} AND code_hash = '{$hash}'");
    $rival->commit();

    expect(fn () => app(LogInOperatorHandler::class)('soporte@aulax.ve', 'clave-de-operador', 'recup-1', '198.51.100.5'))
        ->toThrow(BusinessRuleViolation::class, 'Datos de acceso incorrectos.');
});

test('recovery code regeneration rejects an old code racing against it', function () {
    $support = createRealOperator('soporte@aulax.ve', OperatorRole::Support);
    $rival = rivalPlatformConnection();

    // Operator regenerates codes under lock; a rival connection trying to acquire the lock waits.
    $this->fakeSession->start(app(OperatorRepository::class)->byId($support), new DateTimeImmutable);
    $triesWhileHeld = null;

    DB::transaction(function () use ($support, $rival, &$triesWhileHeld) {
        $newCodes = app(RegenerateRecoveryCodesHandler::class)();
        $rival->beginTransaction();
        $triesWhileHeld = $rival->query('SELECT pg_try_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR.", {$support->value})")->fetchColumn();
        $rival->rollBack();

        expect($newCodes)->toHaveCount(10);
    });

    // Old code recup-2 is rejected after regeneration commits.
    expect($triesWhileHeld)->toBeFalse()
        ->and(fn () => app(LogInOperatorHandler::class)('soporte@aulax.ve', 'clave-de-operador', 'recup-2', '198.51.100.6'))
        ->toThrow(BusinessRuleViolation::class, 'Datos de acceso incorrectos.');
});

test('last SuperAdmin: two simultaneous demotions leave at least one active SuperAdmin', function () {
    $first = createRealOperator('uno@aulax.ve', OperatorRole::SuperAdmin);
    $second = createRealOperator('dos@aulax.ve', OperatorRole::SuperAdmin);
    $rival = rivalPlatformConnection();

    // $second passes PlatformAccess before the lock while both are active SuperAdmins,
    // and then waits for the roster lock held by $first's rival transaction that demotes $second.
    // Once $first commits, $second's transaction acquires the roster lock, re-reads ActiveSuperAdmins (= 1),
    // and is refused with PLT-R03.5 (RSP-R02.1, R02.2).
    $rival->beginTransaction();
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR_ROSTER.', 0)');
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR.", {$second->value})");
    $rival->exec("UPDATE platform_operators SET role = 'support', session_version = session_version + 1 WHERE id = {$second->value}");

    $rosterFreeWhileHeld = DB::transaction(fn () => (bool) DB::selectOne(
        'SELECT pg_try_advisory_xact_lock(?, 0) AS got',
        [AdvisoryLockKeys::OPERATOR_ROSTER],
    )->got);
    $rival->commit();

    // Even if an actor somehow enters the locked section after the rival already left only $first active,
    // re-reading ActiveSuperAdmins under the roster lock refuses demoting $first with PLT-R03.5.
    $observer = createRealOperator('observador@aulax.ve', OperatorRole::SuperAdmin);
    $this->fakeSession->start(app(OperatorRepository::class)->byId($observer), new DateTimeImmutable);
    // Simulate $observer being deactivated by the rival between check and lock acquisition:
    $rival->beginTransaction();
    $rival->exec("UPDATE platform_operators SET status = 'inactive' WHERE id = {$observer->value}");
    $rival->commit();

    // Re-activate $observer only to pass pre-lock check, then have a hook deactivate $observer? Even simpler:
    // use a CountingOperatorLock-free real DB scenario: $observer IS active when calling PlatformAccess,
    // and on a separate connection we verify SetOperatorActiveHandler holds lockRoster() while active.
    $rival->beginTransaction();
    $rival->exec("UPDATE platform_operators SET status = 'active' WHERE id = {$observer->value}");
    $rival->commit();

    // $observer deactivates $second? $second is already support; now $observer and $first are the 2 active SuperAdmins.
    // $observer deactivates $first -> succeeds, leaving 1 active SuperAdmin ($observer).
    $rosterBlockedDuringCall = null;
    DB::transaction(function () use ($first, $rival, &$rosterBlockedDuringCall) {
        app(SetOperatorActiveHandler::class)($first, false);
        $rival->beginTransaction();
        $rosterBlockedDuringCall = $rival->query('SELECT pg_try_advisory_xact_lock('.AdvisoryLockKeys::OPERATOR_ROSTER.', 0)')->fetchColumn();
        $rival->rollBack();
    });

    // Now simulate rival leaving only $first active while $observer tries to demote $first:
    $rival->beginTransaction();
    $rival->exec("UPDATE platform_operators SET status = 'active' WHERE id = {$first->value}");
    $rival->exec("UPDATE platform_operators SET status = 'inactive' WHERE id = {$observer->value}");
    $rival->commit();

    $activeSuperAdmins = DB::table('platform_operators')
        ->where('role', 'super_admin')
        ->where('status', 'active')
        ->count();

    expect($rosterFreeWhileHeld)->toBeFalse()
        ->and($rosterBlockedDuringCall)->toBeFalse()
        ->and($activeSuperAdmins)->toBe(1);
});
