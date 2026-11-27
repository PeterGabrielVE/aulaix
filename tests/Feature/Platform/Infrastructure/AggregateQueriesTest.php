<?php

use AulaX\Audit\Application\ListEntries\LastActivityQuery;
use AulaX\IdentityAccess\Application\Members\AdministratorContactsQuery;
use AulaX\IdentityAccess\Application\Members\MemberCountsQuery;
use AulaX\Platform\Infrastructure\TenantScopedReader;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\ActiveStudentCountQuery;
use AulaX\Tenancy\Application\InstitutionFiles\UsedStorageQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require_once dirname(__DIR__, 3).'/Fixtures/Workspace/home_helpers.php';

/**
 * SPEC-014 PLT-R04.3, R04.4, NF01 and V2: the figures of one institution,
 * from the modules that own them, answered only inside the platform's
 * bounded read.
 */
beforeEach(function () {
    Storage::fake(config('tenancy.files_disk'));
    $this->institution = tenant();
    userWithRole($this->institution, 'Administrador', ['name' => 'Ana Rivas', 'email' => 'ana@colegio.ve']);
    userWithRole($this->institution, 'Docente');
    userWithRole($this->institution, 'Docente');
    useTenant($this->institution);
    seedStudent($this->institution->id, 'V11111111111');
    seedStudent($this->institution->id, 'V22222222222', 'withdrawn');
    DB::table('audit_entries')->insert(['institution_id' => $this->institution->id, 'occurred_at' => '2026-10-20 09:30:00+00', 'event_type' => 'students.student.registered', 'subject_type' => 'student', 'subject_id' => '1', 'origin' => 'web']);
    Storage::disk(config('tenancy.files_disk'))->put("institutions/{$this->institution->id}/logo-abc.png", str_repeat('x', 1500));

    // Another institution, which must never be counted.
    $other = tenant();
    userWithRole($other, 'Docente');
    seedStudent($other->id, 'V99999999999');
    leaveTenant();
});

function inPlatformRead(int $institutionId, callable $read): mixed
{
    return app(TenantScopedReader::class)->read($institutionId, $read);
}

test('counts the active members by role and the active students', function () {
    $figures = inPlatformRead($this->institution->id, fn () => [
        'members' => app(MemberCountsQuery::class)(),
        'students' => app(ActiveStudentCountQuery::class)(),
    ]);

    expect($figures['members'])->toBe(['Administrador' => 1, 'Director' => 0, 'Coordinador' => 0, 'Docente' => 2, 'Representante' => 0, 'Estudiante' => 0])
        ->and($figures['students'])->toBe(1);
});

test('tells the last activity, the stored bytes and the Administradores to notify', function () {
    $figures = inPlatformRead($this->institution->id, fn () => [
        'last' => app(LastActivityQuery::class)(),
        'bytes' => app(UsedStorageQuery::class)(),
        'admins' => app(AdministratorContactsQuery::class)(),
    ]);

    expect($figures['last']?->format('Y-m-d H:i'))->toBe('2026-10-20 09:30')
        ->and($figures['bytes'])->toBe(1500)
        ->and($figures['admins'])->toBe([['name' => 'Ana Rivas', 'email' => 'ana@colegio.ve']]);
});

test('every aggregate query refuses to answer outside a platform read (V2)', function (string $query) {
    useTenant($this->institution);

    expect(fn () => app($query)())->toThrow(AuthorizationDenied::class);
})->with([
    MemberCountsQuery::class,
    AdministratorContactsQuery::class,
    ActiveStudentCountQuery::class,
    LastActivityQuery::class,
    UsedStorageQuery::class,
]);
