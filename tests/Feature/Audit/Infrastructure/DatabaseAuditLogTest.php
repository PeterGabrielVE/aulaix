<?php

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\Audit\Application\ListEntries\AuditEntryView;
use AulaX\Audit\Application\ListEntries\AuditLog;
use AulaX\Audit\Application\ListEntries\AuditLogFilter;
use AulaX\IdentityAccess\Infrastructure\Persistence\AccountModel as User;
use AulaX\Shared\Application\TransactionManager;

/**
 * Records an event at the given UTC time, as the given user (or nobody).
 */
function recordAt(string $when, string $type = 'evaluation.grade.changed', string $subjectId = '1', ?User $actor = null): void
{
    test()->travelTo(new DateTimeImmutable($when, new DateTimeZone('UTC')));
    $actor ? test()->actingAs($actor) : auth()->forgetGuards();

    app(TransactionManager::class)->run(fn () => app(AuditTrail::class)->record(
        AuditEvent::of($type, AuditSubject::of('grade', $subjectId), [FieldChange::of('grade', 14, 16)]),
    ));
}

/**
 * @return list<string>
 */
function subjectIdsOf(array $entries): array
{
    return array_map(fn (AuditEntryView $entry) => $entry->subjectId, $entries);
}

it('lists the newest entries first, one page at a time', function () {
    tenant();
    recordAt('2026-10-01 08:00', subjectId: 'oldest');
    recordAt('2026-10-02 08:00', subjectId: 'middle');
    recordAt('2026-10-03 08:00', subjectId: 'newest');

    $first = app(AuditLog::class)->page(new AuditLogFilter, page: 1, perPage: 2);
    $second = app(AuditLog::class)->page(new AuditLogFilter, page: 2, perPage: 2);

    expect(subjectIdsOf($first->entries))->toBe(['newest', 'middle'])
        ->and(subjectIdsOf($second->entries))->toBe(['oldest'])
        ->and($first->total)->toBe(3);
});

it('filters by date range, inclusive', function () {
    tenant();
    recordAt('2026-09-30 23:59', subjectId: 'before');
    recordAt('2026-10-01 00:00', subjectId: 'first-day');
    recordAt('2026-10-31 23:59', subjectId: 'last-day');
    recordAt('2026-11-01 00:00', subjectId: 'after');

    $page = app(AuditLog::class)->page(new AuditLogFilter(
        from: new DateTimeImmutable('2026-10-01 00:00', new DateTimeZone('UTC')),
        to: new DateTimeImmutable('2026-10-31 23:59', new DateTimeZone('UTC')),
    ), 1, 50);

    expect(subjectIdsOf($page->entries))->toBe(['last-day', 'first-day']);
});

it('filters by actor, event type and affected object', function () {
    $institution = tenant();
    $docente = User::factory()->for($institution)->create();
    recordAt('2026-10-01 08:00', subjectId: 'by-docente', actor: $docente);
    recordAt('2026-10-02 08:00', type: 'evaluation.plan.changed', subjectId: 'plan');
    recordAt('2026-10-03 08:00', subjectId: 'other-grade');

    $log = app(AuditLog::class);

    expect(subjectIdsOf($log->page(new AuditLogFilter(actorId: $docente->id), 1, 50)->entries))->toBe(['by-docente'])
        ->and(subjectIdsOf($log->page(new AuditLogFilter(eventType: 'evaluation.plan.changed'), 1, 50)->entries))->toBe(['plan'])
        ->and(subjectIdsOf($log->page(new AuditLogFilter(subjectType: 'grade', subjectId: 'other-grade'), 1, 50)->entries))->toBe(['other-grade']);
});

it('names the actor, and keeps the entry once the account is gone', function () {
    $institution = tenant();
    $kept = User::factory()->for($institution)->create(['name' => 'Ana Pérez']);
    $deleted = User::factory()->for($institution)->create();
    recordAt('2026-10-01 08:00', subjectId: 'by-ana', actor: $kept);
    recordAt('2026-10-02 08:00', subjectId: 'by-deleted', actor: $deleted);
    $deleted->delete();

    $entries = app(AuditLog::class)->page(new AuditLogFilter, 1, 50)->entries;

    expect($entries[0]->actorId)->toBe($deleted->id)
        ->and($entries[0]->actorName)->toBeNull()
        ->and($entries[1]->actorName)->toBe('Ana Pérez');
});

it('opens one entry with the values before and after each change', function () {
    tenant();
    recordAt('2026-10-01 08:00');
    $id = app(AuditLog::class)->page(new AuditLogFilter, 1, 50)->entries[0]->id;

    $entry = app(AuditLog::class)->find($id);

    expect($entry->changes)->toEqual([FieldChange::of('grade', 14, 16)])
        ->and($entry->occurredAt->format(DATE_ATOM))->toBe('2026-10-01T08:00:00+00:00');
});

it('does not open an entry of another institution', function () {
    tenant();
    recordAt('2026-10-01 08:00');
    $id = app(AuditLog::class)->page(new AuditLogFilter, 1, 50)->entries[0]->id;

    tenant();

    expect(app(AuditLog::class)->find($id))->toBeNull();
});
