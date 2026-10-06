<?php

use App\Support\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1-01 / F1-02: the building blocks every future tenant-scoped table is
 * made of — the `belongsToInstitution()` column macro and
 * RowLevelSecurity::enable() — and a guard that no tenant-scoped table
 * ships without its RLS policy.
 */
pest()->group('rls');

/**
 * Tables that have an institution_id column but deliberately no RLS policy.
 * spatie/laravel-permission reads its own tables before and outside of a
 * tenant request (e.g. caching the permission registry) and scopes them by
 * team itself, using institution_id as the team key.
 */
const RLS_EXEMPT_TABLES = ['roles', 'model_has_roles', 'model_has_permissions'];

function createProbeTable(): void
{
    Schema::create('tenant_probes', function (Blueprint $table) {
        $table->id();
        $table->belongsToInstitution();
        $table->string('name');
    });

    RowLevelSecurity::enable('tenant_probes');
}

test('a new tenant table fills institution_id from the current institution', function () {
    createProbeTable();
    $institution = tenant();

    DB::table('tenant_probes')->insert(['name' => 'sin institution_id explícito']);

    expect(DB::table('tenant_probes')->value('institution_id'))->toBe($institution->id);
});

test('a new tenant table only shows the current institution\'s rows', function () {
    createProbeTable();

    tenant();
    DB::table('tenant_probes')->insert(['name' => 'de A']);

    tenant();
    DB::table('tenant_probes')->insert(['name' => 'de B']);

    expect(DB::table('tenant_probes')->pluck('name')->all())->toBe(['de B']);
});

test('a new tenant table rejects rows for another institution', function () {
    createProbeTable();
    $institutionA = tenant();
    tenant(); // session now points at institution B

    expect(fn () => DB::table('tenant_probes')->insert(['institution_id' => $institutionA->id, 'name' => 'intruso']))
        ->toThrow(QueryException::class);
});

test('a new tenant table indexes institution_id', function () {
    createProbeTable();

    expect(Schema::hasIndex('tenant_probes', ['institution_id']))->toBeTrue();
});

test('every table with an institution_id column enforces Row Level Security', function () {
    $tables = collect(DB::select("
        select c.relname as name, c.relrowsecurity as enabled, c.relforcerowsecurity as forced,
               exists (select 1 from pg_policies p where p.tablename = c.relname and p.policyname = 'tenant_isolation') as has_policy
        from pg_class c
        join information_schema.columns col on col.table_name = c.relname and col.table_schema = 'public'
        where col.column_name = 'institution_id' and c.relkind = 'r'
    "))->reject(fn ($table) => in_array($table->name, RLS_EXEMPT_TABLES));

    expect($tables)->not->toBeEmpty();

    foreach ($tables as $table) {
        expect([$table->enabled, $table->forced, $table->has_policy])
            ->toBe([true, true, true], "La tabla {$table->name} tiene institution_id pero no RLS forzada. Usa RowLevelSecurity::enable('{$table->name}') en su migración.");
    }
});

test('every tenant isolation policy uses the same rule as RowLevelSecurity::enable()', function () {
    createProbeTable();

    $policies = collect(DB::select("select tablename, qual, with_check from pg_policies where policyname = 'tenant_isolation'"))
        ->keyBy('tablename');
    $expected = $policies->pull('tenant_probes');

    expect($policies)->not->toBeEmpty();

    // Postgres stores the parsed expression, so the same rule always reads
    // back as the same text. A hand-written policy that differs (e.g. the
    // old one without nullif(), which failed on an empty tenant) fails here.
    foreach ($policies as $table => $policy) {
        expect([$policy->qual, $policy->with_check])
            ->toBe([$expected->qual, $expected->with_check], "La política de {$table} no coincide con RowLevelSecurity::enable().");
    }
});
