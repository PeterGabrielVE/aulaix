<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-014 PLT-NF01, NF02 (ADR 0010): the operators' tables belong to the
 * platform. While an institution is active they read as empty and refuse
 * every write, like the platform audit log.
 */
pest()->group('rls');

function platformOperatorMigration(): object
{
    return require database_path('migrations/2026_10_26_194129_create_platform_operator_tables.php');
}

function insertOperator(string $email = 'ana@aulax.ve'): int
{
    return DB::table('platform_operators')->insertGetId([
        'name' => 'Ana Rivas', 'email' => $email, 'role' => 'super_admin', 'status' => 'pending',
        'session_version' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('the platform sees and writes its operators with no institution active', function () {
    $id = insertOperator();
    DB::table('platform_operator_invitations')->insert([
        'operator_id' => $id, 'purpose' => 'onboarding', 'token_hash' => hash('sha256', 't'),
        'expires_at' => now()->addHours(48), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('platform_operator_recovery_codes')->insert(['operator_id' => $id, 'code_hash' => hash('sha256', 'c')]);

    expect(DB::table('platform_operators')->count())->toBe(1)
        ->and(DB::table('platform_operator_invitations')->count())->toBe(1)
        ->and(DB::table('platform_operator_recovery_codes')->count())->toBe(1);
});

test('an active institution sees no operator and writes none', function () {
    insertOperator();
    tenant();

    expect(DB::table('platform_operators')->count())->toBe(0)
        ->and(fn () => insertOperator('otro@aulax.ve'))->toThrow(QueryException::class);
});

test('the database refuses an unknown role, status or purpose, and a repeated email', function () {
    $id = insertOperator();

    expect(fn () => DB::table('platform_operators')->where('id', $id)->update(['role' => 'root']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('platform_operators')->where('id', $id)->update(['status' => 'deleted']))->toThrow(QueryException::class)
        ->and(fn () => insertOperator())->toThrow(QueryException::class)
        ->and(fn () => DB::table('platform_operator_invitations')->insert([
            'operator_id' => $id, 'purpose' => 'anything', 'token_hash' => 'x', 'expires_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]))->toThrow(QueryException::class);
});

test('the migration rolls back and applies again', function () {
    platformOperatorMigration()->down();

    expect(Schema::hasTable('platform_operators'))->toBeFalse()
        ->and(Schema::hasTable('platform_operator_invitations'))->toBeFalse()
        ->and(Schema::hasTable('platform_operator_recovery_codes'))->toBeFalse();

    platformOperatorMigration()->up();

    expect(Schema::hasTable('platform_operators'))->toBeTrue();
});
