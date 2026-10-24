<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-005 AUD-R03: an audit entry can never be changed or removed, not even
 * with raw SQL through the application's own database role.
 */
function storedEntry(string $table): void
{
    $table === 'audit_entries' ? tenant() : RowLevelSecurity::clearInstitution();

    DB::table($table)->insert([
        'occurred_at' => '2026-10-11 12:00:00+00',
        'event_type' => 'evaluation.grade.changed',
        'subject_type' => 'grade',
        'subject_id' => '1',
        'origin' => 'system',
        'changes' => '{"grade": {"from": 14, "to": 16}}',
    ]);
}

dataset('audit tables', ['audit_entries', 'platform_audit_entries']);

test('an audit entry can not be modified', function (string $table) {
    storedEntry($table);

    expect(fn () => DB::statement("update {$table} set event_type = 'tampered.event.type'"))
        ->toThrow(QueryException::class, 'immutable');
})->with('audit tables');

test('an audit entry can not be deleted', function (string $table) {
    storedEntry($table);

    expect(fn () => DB::statement("delete from {$table}"))->toThrow(QueryException::class, 'immutable');
})->with('audit tables');

test('an audit table can not be truncated', function (string $table) {
    storedEntry($table);

    expect(fn () => DB::statement("truncate {$table}"))->toThrow(QueryException::class, 'immutable');
})->with('audit tables');
