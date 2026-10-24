<?php

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\Audit\Application\ListEntries\AuditLog;
use AulaX\Audit\Application\ListEntries\AuditLogFilter;
use AulaX\Shared\Application\TransactionManager;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-005 AUD-NF01, AUD-NF02. Slow, so excluded from the default run and
 * from CI (phpunit.xml): run with `php artisan test --group=perf`.
 */
pest()->group('perf');

const YEAR_OF_ENTRIES = 200_000;

/**
 * A year of one institution's audit log, spread evenly over 2025-10 → 2026-10.
 */
function seedYearOfEntries(): void
{
    DB::statement("
        insert into audit_entries (occurred_at, event_type, subject_type, subject_id, actor_user_id, origin, changes)
        select timestamptz '2025-10-11 00:00:00+00' + (n * interval '1 year' / ?),
               (array['evaluation.grade.changed', 'evaluation.plan.changed', 'identity.role.changed'])[1 + n % 3],
               'grade', (n % 5000)::text, 1 + n % 40, 'web', '{\"grade\": {\"from\": 14, \"to\": 16}}'
        from generate_series(1, ?) as n
    ", [YEAR_OF_ENTRIES, YEAR_OF_ENTRIES]);

    DB::statement('analyze audit_entries');
}

/**
 * @return float Milliseconds the callback took.
 */
function elapsedMs(Closure $callback): float
{
    $start = hrtime(true);
    $callback();

    return (hrtime(true) - $start) / 1e6;
}

test('a year of audit log answers any combination of filters in under 2 seconds', function (AuditLogFilter $filter) {
    tenant();
    seedYearOfEntries();

    $elapsed = elapsedMs(fn () => app(AuditLog::class)->page($filter, page: 1, perPage: 50));

    expect($elapsed)->toBeLessThan(2000.0);
})->with([
    'no filter' => [new AuditLogFilter],
    'last month' => [new AuditLogFilter(from: new DateTimeImmutable('2026-09-11T00:00:00Z'), to: new DateTimeImmutable('2026-10-11T00:00:00Z'))],
    'one actor and event type' => [new AuditLogFilter(actorId: 7, eventType: 'evaluation.grade.changed')],
    'one object' => [new AuditLogFilter(subjectType: 'grade', subjectId: '1234')],
]);

test('recording an audit entry adds under 20 ms to a change', function () {
    tenant();
    seedYearOfEntries();
    $event = AuditEvent::of('evaluation.grade.changed', AuditSubject::of('grade', 1), [FieldChange::of('grade', 14, 16)]);

    $elapsed = elapsedMs(fn () => app(TransactionManager::class)->run(function () use ($event) {
        foreach (range(1, 100) as $attempt) {
            app(AuditTrail::class)->record($event);
        }
    }));

    expect($elapsed / 100)->toBeLessThan(20.0);
});
