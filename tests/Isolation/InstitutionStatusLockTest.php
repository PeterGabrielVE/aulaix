<?php

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Shared\Infrastructure\AdvisoryLockKeys;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\Transfer\ApproveTransferHandler;
use AulaX\Tenancy\Application\Administration\SetInstitutionStatusHandler;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Students\SwitchableStudentPermissions;

/**
 * SPEC-017 RSP-R08.1, NF01 (ADR 0014): two real PostgreSQL connections racing
 * between deactivating the destination institution (exclusive lock 1001) and
 * approving a transfer into it (shared lock 1001).
 */
beforeEach(function () {
    app()->instance(AuditTrail::class, new RecordingAuditTrail);
    app()->instance(StudentPermissions::class, new SwitchableStudentPermissions);

    $this->origin = Institution::factory()->create(['name' => 'Colegio Norte', 'subdomain' => 'norte-lock']);
    $this->destination = Institution::factory()->create(['name' => 'Colegio Sur', 'subdomain' => 'sur-lock']);
    $this->originContext = TenantContext::of(InstitutionMapper::toDomain($this->origin));

    DB::transaction(function () {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $this->origin->id]);
        $studentId = DB::table('students')->insertGetId([
            'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2019-03-04',
            'nationality' => 'V', 'identification_type' => 'school_id', 'identification' => 'V11915432100',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $guardianId = DB::table('guardians')->insertGetId([
            'given_names' => 'María', 'family_names' => 'Mora', 'document_type' => 'national_id',
            'document' => 'V15432100', 'phone' => '0414', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('guardianships')->insert([
            'student_id' => $studentId, 'guardian_id' => $guardianId, 'relationship' => 'mother',
            'is_legal' => true, 'is_primary' => true,
        ]);
    });

    $this->transferId = DB::transaction(function () {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $this->destination->id]);

        return DB::table('transfer_requests')->insertGetId([
            'destination_name' => 'Colegio Sur',
            'identification' => 'V11915432100',
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    });
});

function rivalStatusConnection(): PDO
{
    $config = config('database.connections.pgsql');
    $host = $config['host'] === 'pgbouncer' ? 'postgres' : $config['host'];
    $port = $config['host'] === 'pgbouncer' ? 5432 : $config['port'];

    return new PDO("pgsql:host={$host};port={$port};dbname={$config['database']}", $config['username'], $config['password']);
}

test('deactivation under exclusive lock blocks transfer approval and leaves both institutions untouched', function () {
    $rival = rivalStatusConnection();

    // Rival acquires the exclusive status lock and deactivates the destination.
    $rival->beginTransaction();
    $rival->query('SELECT pg_advisory_xact_lock('.AdvisoryLockKeys::INSTITUTION_STATUS.", {$this->destination->id})");
    $rival->exec("UPDATE institutions SET status = 'inactive' WHERE id = {$this->destination->id}");

    $sharedLockAvailableWhileHeld = DB::transaction(fn () => (bool) DB::selectOne(
        'SELECT pg_try_advisory_xact_lock_shared(?, ?) AS got',
        [AdvisoryLockKeys::INSTITUTION_STATUS, $this->destination->id],
    )->got);
    $rival->commit();

    // After deactivation commits, approving the transfer re-reads 'inactive' under the shared lock and refuses.
    expect($sharedLockAvailableWhileHeld)->toBeFalse()
        ->and(fn () => app(TenantActivator::class)->within($this->originContext, fn () => app(ApproveTransferHandler::class)($this->transferId)))
        ->toThrow(BusinessRuleViolation::class, 'El plantel destino no puede recibir el traslado por ahora.');

    $originStatus = DB::transaction(function () {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $this->origin->id]);

        return DB::table('students')->value('status');
    });
    $destinationStudents = DB::transaction(function () {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $this->destination->id]);

        return DB::table('students')->count();
    });

    expect($originStatus)->toBe('active')
        ->and($destinationStudents)->toBe(0);
});

test('in-flight transfer holds the shared status lock so deactivation waits for it', function () {
    $rival = rivalStatusConnection();
    $exclusiveAvailableDuringTransfer = null;

    app(TenantActivator::class)->within($this->originContext, function () use ($rival, &$exclusiveAvailableDuringTransfer) {
        app(ApproveTransferHandler::class)($this->transferId);

        $rival->beginTransaction();
        $exclusiveAvailableDuringTransfer = $rival->query(
            'SELECT pg_try_advisory_xact_lock('.AdvisoryLockKeys::INSTITUTION_STATUS.", {$this->destination->id})"
        )->fetchColumn();
        $rival->rollBack();
    });

    // After commit, deactivation acquires the exclusive lock cleanly.
    $deactivated = DB::transaction(fn () => app(SetInstitutionStatusHandler::class)(InstitutionId::fromInt($this->destination->id), false));

    expect($exclusiveAvailableDuringTransfer)->toBeFalse()
        ->and($deactivated)->toBeTrue();
});
