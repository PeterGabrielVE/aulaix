<?php

use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Domain\OverCapacity;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Enrollment\FakeEnrollmentPermissions;

/**
 * SPEC-010 MAT-R02.4, NF03: two managers for the last place of a section,
 * on two real connections. The calendar lock (ADR 0007) makes the second
 * wait for the first, and then it finds the section full.
 */
beforeEach(function () {
    app()->instance(EnrollmentPermissions::class, new FakeEnrollmentPermissions);
    // The audit log is immutable: isolation tests, which really commit, must not write to it.
    app()->instance(AuditTrail::class, new RecordingAuditTrail);
    $this->institution = Institution::factory()->create(['subdomain' => 'norte']);
    $this->context = TenantContext::of(InstitutionMapper::toDomain($this->institution));

    [$this->year, $this->section, $this->first, $this->second] = DB::transaction(function () {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $this->institution->id]);
        $year = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'active']);
        $section = DB::table('sections')->insertGetId(['school_year_id' => $year, 'grade' => 'primaria_3', 'letter' => 'A', 'capacity' => 1]);
        $student = fn (string $id) => DB::table('students')->insertGetId([
            'given_names' => 'Ana', 'family_names' => 'Pérez', 'sex' => 'female', 'birth_date' => '2018-03-10',
            'nationality' => 'V', 'identification_type' => 'national_id', 'identification' => $id, 'status' => 'active',
        ]);

        return [$year, $section, $student('V30111222'), $student('V30333444')];
    });
});

/**
 * The other manager's connection, apart from the application's.
 */
function otherManager(): PDO
{
    $config = config('database.connections.pgsql');

    return new PDO("pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}", $config['username'], $config['password']);
}

function enrollConcurrently(int $student): void
{
    $test = test();
    app(EnrollStudentHandler::class)(new EnrollStudentCommand($student, $test->year, $test->section, '2026-09-16', null, null, 1));
}

test('while one enrollment holds the last place, the other waits; then it finds the section full', function () {
    $other = otherManager();
    $triesWhileHeld = null;

    app(TenantActivator::class)->within($this->context, function () use ($other, &$triesWhileHeld) {
        enrollConcurrently($this->first);

        $other->beginTransaction();
        $other->query("select set_config('app.current_institution_id', '{$this->institution->id}', true)");
        $triesWhileHeld = $other->query("select pg_try_advisory_xact_lock(7007, {$this->institution->id})")->fetchColumn();
        $other->rollBack();
    });

    $other->beginTransaction();
    $other->query("select set_config('app.current_institution_id', '{$this->institution->id}', true)");
    $takesAfterCommit = $other->query("select pg_try_advisory_xact_lock(7007, {$this->institution->id})")->fetchColumn();
    $enrolledAfterCommit = $other->query("select count(*) from enrollments where section_id = {$this->section} and ends_on is null")->fetchColumn();
    $other->rollBack();

    expect($triesWhileHeld)->toBeFalse()
        ->and($takesAfterCommit)->toBeTrue()
        ->and((int) $enrolledAfterCommit)->toBe(1)
        ->and(fn () => app(TenantActivator::class)->within($this->context, fn () => enrollConcurrently($this->second)))
        ->toThrow(OverCapacity::class);
});
