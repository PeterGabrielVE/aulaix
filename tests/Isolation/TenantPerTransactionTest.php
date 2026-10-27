<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * SPEC-015 RLT-R01: an institution's Row Level Security lasts as long as
 * the request that set it, and never reaches the next unit of work on the
 * same connection. These requests really commit: nothing wraps them.
 */
beforeEach(function () {
    $this->norte = Institution::factory()->create(['subdomain' => 'norte']);
    $this->sur = Institution::factory()->create(['subdomain' => 'sur']);
    $this->norteYear = isolatedYearOf($this->norte);
    $this->surYear = isolatedYearOf($this->sur);

    Route::domain('{tenant}.'.config('app.domain'))->middleware(['web', 'tenant'])->group(function () {
        Route::get('/__isolation/years', fn () => DB::table('school_years')->pluck('id'));
        Route::get('/__isolation/fail', function () {
            DB::table('school_years')->pluck('id');

            throw new RuntimeException('The request fails half way.');
        });
        Route::get('/__isolation/savepoint', function () {
            try {
                DB::transaction(function () {
                    DB::table('school_years')->update(['status' => 'active']);

                    throw new RuntimeException('Only this savepoint is undone.');
                });
            } catch (RuntimeException) {
            }

            return DB::table('school_years')->pluck('id');
        });
    });
    Route::domain(config('app.domain'))->middleware('web')->get('/__isolation/years', fn () => DB::table('school_years')->pluck('id'));
});

/**
 * A school year of the institution, committed with its RLS set for that
 * transaction only.
 */
function isolatedYearOf(Institution $institution): int
{
    return DB::transaction(function () use ($institution) {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $institution->id]);

        return DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
    });
}

function connectionInstitution(): string
{
    return (string) DB::scalar("select current_setting('app.current_institution_id', true)");
}

test('a request of an institution sees its own rows, even outside any transaction of its code', function () {
    $this->get(tenantUrl($this->norte, '/__isolation/years'))->assertOk()->assertExactJson([$this->norteYear]);
});

test('after a request the connection is left with no institution, and sees nothing of it', function () {
    $this->get(tenantUrl($this->norte, '/__isolation/years'))->assertOk();

    expect(connectionInstitution())->toBe('')
        ->and(DB::table('school_years')->count())->toBe(0);
});

test('a request of another institution on the same connection sees nothing of the first', function () {
    $this->get(tenantUrl($this->norte, '/__isolation/years'))->assertOk();

    $this->get(tenantUrl($this->sur, '/__isolation/years'))->assertExactJson([$this->surYear]);
});

test('a request that fails with a server error leaves no institution behind', function () {
    $this->withoutExceptionHandling([RuntimeException::class]);
    $this->get(tenantUrl($this->norte, '/__isolation/fail'))->assertServerError();

    expect(connectionInstitution())->toBe('');
});

test('a savepoint rolled back inside a request keeps the institution of the request', function () {
    $this->get(tenantUrl($this->norte, '/__isolation/savepoint'))->assertOk()->assertExactJson([$this->norteYear]);
});

test('the central domain, right after a request of an institution, sees nothing', function () {
    $this->get(tenantUrl($this->norte, '/__isolation/years'))->assertOk();

    $this->get(centralUrl('/__isolation/years'))->assertOk()->assertExactJson([]);
});
