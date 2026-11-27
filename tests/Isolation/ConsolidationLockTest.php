<?php

use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Application\Consolidation\CaseResolutionRefused;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationDecision;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseCommand;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseHandler;
use AulaX\IdentityAccess\Infrastructure\Notifications\AccountsConsolidatedNotice;
use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRepository;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Shared\Infrastructure\AdvisoryLockKeys;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Fixtures\Audit\RecordingAuditTrail;

/**
 * SPEC-017 RSP-R04.1 – R04.4, NF01 (ADR 0014): two concurrent resolutions of
 * the same consolidation case on two real PostgreSQL connections. The advisory
 * lock keyed by (4011, hashtext(lower(email))) serializes them so separated
 * accounts can never be merged afterwards.
 */
beforeEach(function () {
    Notification::fake();
    $this->audit = new RecordingAuditTrail;
    app()->instance(AuditTrail::class, $this->audit);

    // Active operator authorized to resolve cases.
    $repository = app(OperatorRepository::class);
    $locks = app(OperatorLock::class);
    DB::transaction(function () use ($repository, $locks) {
        $operator = Operator::invite('Luis', OperatorEmail::fromString('luis@aulax.ve'), OperatorRole::Support);
        $operator->activate(Hash::make('clave'), 'JBSWY3DPEHPK3PXP', step: 1);
        $id = $repository->save($operator);
        $locks->lockOperator($id);
    });
});

function rivalConsolidationConnection(): PDO
{
    $config = config('database.connections.pgsql');
    $host = $config['host'] === 'pgbouncer' ? 'postgres' : $config['host'];
    $port = $config['host'] === 'pgbouncer' ? 5432 : $config['port'];

    return new PDO("pgsql:host={$host};port={$port};dbname={$config['database']}", $config['username'], $config['password']);
}

/**
 * @return array{0: int, 1: int}
 */
function createReviewAccounts(string $email): array
{
    $norte = Institution::factory()->create(['subdomain' => 'norte-'.substr(md5($email), 0, 6)]);
    $sur = Institution::factory()->create(['subdomain' => 'sur-'.substr(md5($email), 0, 6)]);

    $older = DB::table('users')->insertGetId([
        'name' => 'Ana Pérez', 'email' => $email, 'password' => Hash::make('clave-1'),
        'consolidation_state' => 'review', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $newer = DB::table('users')->insertGetId([
        'name' => 'Ana María Pérez', 'email' => $email, 'password' => Hash::make('clave-2'),
        'consolidation_state' => 'review', 'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    foreach ([[$norte->id, $older], [$sur->id, $newer]] as [$instId, $userId]) {
        DB::transaction(function () use ($instId, $userId) {
            DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $instId]);
            DB::table('institution_memberships')->insert([
                'institution_id' => $instId, 'user_id' => $userId, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    return [$older, $newer];
}

test('separate wins and blocks a concurrent consolidation with no email or session revocation', function () {
    $email = 'separar-primero@example.com';
    [$older, $newer] = createReviewAccounts($email);
    DB::table('sessions')->insert(['id' => 'keep-me-open', 'user_id' => $newer, 'payload' => '', 'last_activity' => time()]);
    $rival = rivalConsolidationConnection();

    // Rival takes the email advisory lock and marks both accounts separated.
    $rival->beginTransaction();
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::CONSOLIDATION.", hashtext(lower('{$email}')))");
    $rival->exec("UPDATE users SET consolidation_state = 'separated' WHERE id IN ({$older}, {$newer})");

    $lockFreeWhileHeld = DB::transaction(fn () => (bool) DB::selectOne(
        'SELECT pg_try_advisory_xact_lock(?, hashtext(lower(?))) AS got',
        [AdvisoryLockKeys::CONSOLIDATION, $email],
    )->got);
    $rival->commit();

    // Now the racing consolidation runs: re-reads 'separated', refuses, sends no email, keeps session open.
    expect($lockFreeWhileHeld)->toBeFalse()
        ->and(fn () => app(ResolveConsolidationCaseHandler::class)(new ResolveConsolidationCaseCommand(
            'luis@aulax.ve', [$older, $newer], ConsolidationDecision::Consolidate, 'Verificada', 'TICKET-1', dryRun: false,
        )))->toThrow(CaseResolutionRefused::class);

    expect(DB::table('users')->where('email', $email)->pluck('consolidation_state')->all())->toBe(['separated', 'separated'])
        ->and(DB::table('sessions')->where('id', 'keep-me-open')->exists())->toBeTrue()
        ->and($this->audit->types())->toBe(['identity.consolidation.refused']);
    Notification::assertNothingSentTo(new AnonymousNotifiable, AccountsConsolidatedNotice::class);
    DB::table('sessions')->where('id', 'keep-me-open')->delete();
});

test('consolidate wins and blocks a concurrent separate resolution', function () {
    $email = 'consolidar-primero@example.com';
    [$older, $newer] = createReviewAccounts($email);
    $rival = rivalConsolidationConnection();

    // Application consolidates inside a transaction while rival probes the email lock.
    $lockFreeDuringConsolidation = null;
    DB::transaction(function () use ($email, $older, $newer, $rival, &$lockFreeDuringConsolidation) {
        app(ResolveConsolidationCaseHandler::class)(new ResolveConsolidationCaseCommand(
            'luis@aulax.ve', [$older, $newer], ConsolidationDecision::Consolidate, 'Cédula verificada', 'TICKET-2', dryRun: false,
        ));

        $rival->beginTransaction();
        $lockFreeDuringConsolidation = $rival->query(
            'SELECT pg_try_advisory_xact_lock('.AdvisoryLockKeys::CONSOLIDATION.", hashtext(lower('{$email}')))"
        )->fetchColumn();
        $rival->rollBack();
    });

    // Racing 'separate' arrives after commit: re-reads, sees only survivor with null consolidation_state, refuses.
    expect($lockFreeDuringConsolidation)->toBeFalse()
        ->and(fn () => app(ResolveConsolidationCaseHandler::class)(new ResolveConsolidationCaseCommand(
            'luis@aulax.ve', [$older, $newer], ConsolidationDecision::Separate, 'Distintas personas', null, dryRun: false,
        )))->toThrow(CaseResolutionRefused::class);

    expect(DB::table('users')->where('email', $email)->count())->toBe(1)
        ->and(DB::table('users')->where('id', $older)->value('consolidation_state'))->toBeNull()
        ->and($this->audit->types())->toContain('identity.consolidation.refused');
});
