<?php

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\Audit\Infrastructure\DatabaseAuditTrail;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Clock;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function gradeChanged(): AuditEvent
{
    return AuditEvent::of('evaluation.grade.changed', AuditSubject::of('grade', 42), [FieldChange::of('grade', 14, 16)]);
}

it('records a change in the active institution log with who, when, what and the values', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-11 14:30:00', new DateTimeZone('UTC')));
    $institution = tenant();
    $this->actingAs(User::factory()->for($institution)->create());
    $actorId = auth()->id();

    app(TransactionManager::class)->run(fn () => app(AuditTrail::class)->record(gradeChanged()));

    $entry = DB::table('audit_entries')->sole();
    expect($entry->institution_id)->toBe($institution->id)
        ->and((new DateTimeImmutable($entry->occurred_at))->format(DATE_ATOM))->toBe('2026-10-11T14:30:00+00:00')
        ->and([$entry->event_type, $entry->subject_type, $entry->subject_id])->toBe(['evaluation.grade.changed', 'grade', '42'])
        ->and($entry->actor_user_id)->toBe($actorId)
        ->and($entry->origin)->toBe('system')
        // jsonb stores keys in its own order, so compare regardless of it.
        ->and(json_decode($entry->changes, true))->toEqual(['grade' => ['from' => 14, 'to' => 16]]);
});

it('records a global identity change in the platform log only', function () {
    RowLevelSecurity::clearInstitution();

    app(TransactionManager::class)->run(fn () => app(AuditTrail::class)->recordForPlatform(
        AuditEvent::of('identity.password.changed', AuditSubject::of('account', 7)),
    ));

    expect(DB::table('platform_audit_entries')->value('event_type'))->toBe('identity.password.changed')
        ->and(DB::table('platform_audit_entries')->value('changes'))->toBe('{}');
});

it('leaves no entry when the audited change rolls back', function () {
    tenant();

    try {
        app(TransactionManager::class)->run(function () {
            app(AuditTrail::class)->record(gradeChanged());

            throw new RuntimeException('the change fails after being audited');
        });
    } catch (RuntimeException) {
        // The rollback is the point of the test.
    }

    expect(DB::table('audit_entries')->count())->toBe(0);
});

it('does not commit the change when its entry can not be stored', function () {
    tenant();

    expect(fn () => app(TransactionManager::class)->run(function () {
        InstitutionModel::factory()->create(['subdomain' => 'cambio-auditado']);

        // With an institution active, the platform log rejects the write.
        app(AuditTrail::class)->recordForPlatform(gradeChanged());
    }))->toThrow(QueryException::class);

    $this->assertDatabaseMissing('institutions', ['subdomain' => 'cambio-auditado']);
});

it('refuses to record outside a transaction, where it could not be atomic', function () {
    // A second connection to the same database, outside the transaction
    // RefreshDatabase wraps every test in.
    config(['database.connections.pgsql_outside_test_transaction' => config('database.connections.pgsql')]);
    $trail = new DatabaseAuditTrail(
        DB::connection('pgsql_outside_test_transaction'),
        app(AuditContext::class),
        app(Clock::class),
    );

    expect(fn () => $trail->record(gradeChanged()))->toThrow(LogicException::class, 'transaction');
});
