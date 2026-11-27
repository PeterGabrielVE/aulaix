<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-005 AUD-R04: each institution only ever sees its own audit log, and
 * the platform log (global identity changes) is invisible to all of them —
 * enforced by PostgreSQL, so raw SQL is used on purpose.
 */
pest()->group('rls');

function rawAuditRow(): array
{
    return [
        'occurred_at' => '2026-10-11 12:00:00+00',
        'event_type' => 'evaluation.grade.changed',
        'subject_type' => 'grade',
        'subject_id' => '1',
        'origin' => 'system',
        'changes' => '{}',
    ];
}

test('an institution never sees the audit entries of another', function () {
    tenant();
    DB::table('audit_entries')->insert(rawAuditRow());

    tenant();

    expect(DB::table('audit_entries')->count())->toBe(0);
});

test('an audit entry is stored with the active institution', function () {
    $institution = tenant();

    DB::table('audit_entries')->insert(rawAuditRow());

    expect(DB::table('audit_entries')->value('institution_id'))->toBe($institution->id);
});

test('the platform log is invisible while an institution is active', function () {
    RowLevelSecurity::clearInstitution();
    DB::table('platform_audit_entries')->insert(rawAuditRow());

    tenant();

    expect(DB::table('platform_audit_entries')->count())->toBe(0);
});

test('nothing can be written to the platform log while an institution is active', function () {
    tenant();

    expect(fn () => DB::table('platform_audit_entries')->insert(rawAuditRow()))
        ->toThrow(QueryException::class, 'row-level security');
});

test('the platform log is readable with no institution active', function () {
    RowLevelSecurity::clearInstitution();
    DB::table('platform_audit_entries')->insert(rawAuditRow());

    expect(DB::table('platform_audit_entries')->count())->toBe(1);
});
