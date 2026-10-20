<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-007 CAL-NF02, ADR 0007: the database itself keeps the rules across
 * years and terms, whatever the code does. Raw SQL on purpose.
 */
function rawYear(string $start, string $end, string $status = 'planned'): int
{
    return DB::table('school_years')->insertGetId(['starts_on' => $start, 'ends_on' => $end, 'status' => $status]);
}

function rawTerm(int $yearId, int $number, string $status = 'pending'): void
{
    DB::table('terms')->insert(['school_year_id' => $yearId, 'number' => $number, 'starts_on' => '2026-09-16', 'ends_on' => '2026-12-15', 'status' => $status]);
}

/**
 * In a savepoint, so the refused statement does not abort the test's transaction.
 */
function refused(Closure $statement): void
{
    expect(fn () => DB::transaction($statement))->toThrow(QueryException::class);
}

test('an institution has one active year at most', function () {
    tenant();
    rawYear('2025-09-16', '2026-07-15', 'active');

    refused(fn () => rawYear('2026-09-16', '2027-07-15', 'active'));
});

test('an institution has one planned year at most', function () {
    tenant();
    rawYear('2026-09-16', '2027-07-15');

    refused(fn () => rawYear('2027-09-16', '2028-07-15'));
});

test('the years of an institution never overlap, sharing a single day included', function () {
    tenant();
    rawYear('2025-09-16', '2026-07-15', 'closed');

    refused(fn () => rawYear('2026-07-15', '2027-06-10'));
    expect(rawYear('2026-07-16', '2027-06-10'))->toBeInt();
});

test('different institutions keep their own years, overlapping or not', function () {
    tenant();
    rawYear('2026-09-16', '2027-07-15', 'active');
    tenant();

    expect(rawYear('2026-09-16', '2027-07-15', 'active'))->toBeInt();
});

test('a year ends after it starts and has a known status', function () {
    tenant();

    refused(fn () => rawYear('2026-09-16', '2026-09-16'));
    refused(fn () => rawYear('2026-09-16', '2027-07-15', 'paused'));
});

test('a year has terms 1 to 3, each once, and one open at most', function () {
    tenant();
    $year = rawYear('2026-09-16', '2027-07-15', 'active');
    rawTerm($year, 1, 'open');

    refused(fn () => rawTerm($year, 1));
    refused(fn () => rawTerm($year, 4));
    refused(fn () => rawTerm($year, 2, 'open'));
    expect(fn () => rawTerm($year, 2))->not->toThrow(QueryException::class);
});
