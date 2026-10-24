<?php

use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-007 CAL-R08.2: school years and terms are isolated by the database
 * (S-01). Raw SQL on purpose.
 */
pest()->group('rls');

function isolatedYear(): int
{
    $year = DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
    DB::table('terms')->insert(['school_year_id' => $year, 'number' => 1, 'starts_on' => '2026-09-16', 'ends_on' => '2026-12-15', 'status' => 'pending']);

    return $year;
}

test('an institution only sees its own years and terms', function () {
    tenant();
    isolatedYear();
    tenant();
    $own = isolatedYear();

    expect(DB::table('school_years')->pluck('id')->all())->toBe([$own])
        ->and(DB::table('terms')->pluck('school_year_id')->unique()->values()->all())->toBe([$own]);
});

test('with no institution active nothing is visible or writable', function () {
    tenant();
    isolatedYear();
    RowLevelSecurity::clearInstitution();

    expect(DB::table('school_years')->count())->toBe(0)
        ->and(DB::table('terms')->count())->toBe(0)
        ->and(fn () => DB::transaction(fn () => DB::table('school_years')->insert(['institution_id' => 1, 'starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned'])))
        ->toThrow(QueryException::class);
});

test('another institution\'s year can not be changed, even with raw SQL', function () {
    tenant();
    $foreign = isolatedYear();
    tenant();

    expect(DB::table('school_years')->where('id', $foreign)->update(['status' => 'closed']))->toBe(0)
        ->and(DB::table('school_years')->where('id', $foreign)->delete())->toBe(0);
});
