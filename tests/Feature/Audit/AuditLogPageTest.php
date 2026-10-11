<?php

use App\Models\User;
use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\Shared\Application\TransactionManager;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-005 AUD-R05, AUD-R06: the institution's audit log screen.
 */
function auditedChange(string $when, string $type = 'evaluation.grade.changed', ?User $actor = null): int
{
    test()->travelTo(new DateTimeImmutable($when, new DateTimeZone('UTC')));
    $actor ? test()->actingAs($actor) : auth()->forgetGuards();

    app(TransactionManager::class)->run(fn () => app(AuditTrail::class)->record(
        AuditEvent::of($type, AuditSubject::of('grade', 42), [FieldChange::of('grade', 14, 16)]),
    ));

    return (int) DB::table('audit_entries')->max('id');
}

test('guests are sent to the login screen', function () {
    $institution = tenant();

    $this->get(tenantUrl($institution, '/bitacora'))->assertRedirect(route('login'));
});

test('a user without the ver-bitacora permission gets 403', function () {
    $institution = tenant();

    $this->actingAs(userWithRole($institution, 'Docente'))
        ->get(tenantUrl($institution, '/bitacora'))
        ->assertForbidden();
});

test('a director sees the log newest first, fifty per page', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    auditedChange('2026-10-01 08:00', 'evaluation.grade.changed');
    auditedChange('2026-10-02 08:00', 'evaluation.plan.changed');

    $this->actingAs($director)->get(tenantUrl($institution, '/bitacora'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audit/Index')
            ->has('entries.data', 2)
            ->where('entries.data.0.eventType', 'evaluation.plan.changed')
            ->where('entries.data.1.eventType', 'evaluation.grade.changed')
            ->where('entries.perPage', 50)
            ->where('entries.total', 2)
        );
});

test('the log can be filtered', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    auditedChange('2026-10-01 08:00', 'evaluation.grade.changed');
    auditedChange('2026-10-02 08:00', 'evaluation.plan.changed');

    $this->actingAs($director)->get(tenantUrl($institution, '/bitacora?event_type=evaluation.grade.changed'))
        ->assertInertia(fn ($page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.eventType', 'evaluation.grade.changed')
            ->where('filters.event_type', 'evaluation.grade.changed')
        );
});

test('a date range that ends before it starts is rejected', function () {
    $institution = tenant();

    $this->actingAs(userWithRole($institution, 'Director'))
        ->get(tenantUrl($institution, '/bitacora?from=2026-10-31&to=2026-10-01'))
        ->assertSessionHasErrors('to');
});

test('an entry shows each change, its actor and the time in Venezuela', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $docente = User::factory()->for($institution)->create(['name' => 'Ana Pérez']);
    $id = auditedChange('2026-10-11 14:30', actor: $docente);

    $this->actingAs($director)->get(tenantUrl($institution, "/bitacora/{$id}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Audit/Show')
            ->where('entry.actor', 'Ana Pérez')
            ->where('entry.occurredAt', '11/10/2026 10:30')
            ->where('entry.changes.0', ['field' => 'grade', 'from' => 14, 'to' => 16])
        );
});

test('an entry whose actor account is gone names it as deleted', function () {
    $institution = tenant();
    $director = userWithRole($institution, 'Director');
    $docente = User::factory()->for($institution)->create();
    $id = auditedChange('2026-10-11 14:30', actor: $docente);
    $docente->delete();

    $this->actingAs($director)->get(tenantUrl($institution, "/bitacora/{$id}"))
        ->assertInertia(fn ($page) => $page->where('entry.actor', 'Cuenta eliminada'));
});

test('an entry of another institution is not found', function () {
    tenant();
    $id = auditedChange('2026-10-11 14:30');
    $other = tenant();

    $this->actingAs(userWithRole($other, 'Director'))
        ->get(tenantUrl($other, "/bitacora/{$id}"))
        ->assertNotFound();
});

test('the menu offers the log only to those who may view it', function (string $role, bool $canView) {
    $institution = tenant();

    $this->actingAs(userWithRole($institution, $role))
        ->get(tenantUrl($institution, '/profile'))
        ->assertInertia(fn ($page) => $page->where('auth.can.viewAudit', $canView));
})->with([
    'Director' => ['Director', true],
    'Docente' => ['Docente', false],
]);
