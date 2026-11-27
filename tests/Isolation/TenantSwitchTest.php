<?php

use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionMapper;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * SPEC-015 RLT-R02: a use case that acts in another institution for a
 * moment gives the request's institution back, also when it fails half
 * way, and leaves nothing behind once the request ends.
 */
beforeEach(function () {
    $this->norte = Institution::factory()->create(['subdomain' => 'norte']);
    $this->sur = Institution::factory()->create(['subdomain' => 'sur']);
    $this->norteYear = switchYearOf($this->norte);
    $sur = TenantContext::of(InstitutionMapper::toDomain($this->sur));

    Route::domain('{tenant}.'.config('app.domain'))->middleware(['web', 'tenant'])->get('/__isolation/switch', function () use ($sur) {
        try {
            app(TenantActivator::class)->within($sur, function () {
                DB::table('school_years')->insert(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);

                throw new RuntimeException('The switch fails half way.');
            });
        } catch (RuntimeException) {
        }

        return ['years' => DB::table('school_years')->pluck('id'), 'institution' => RowLevelSecurity::currentSetting()];
    });
});

function switchYearOf(Institution $institution): int
{
    return DB::transaction(function () use ($institution) {
        DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $institution->id]);

        return DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']);
    });
}

test('a switch that fails half way gives the request its institution back, and writes nothing in the other', function () {
    $this->get(tenantUrl($this->norte, '/__isolation/switch'))->assertOk()->assertExactJson([
        'years' => [$this->norteYear],
        'institution' => (string) $this->norte->id,
    ]);

    expect(RowLevelSecurity::currentSetting())->toBe('')
        ->and(DB::transaction(function () {
            DB::select("select set_config('app.current_institution_id', ?, true)", [(string) $this->sur->id]);

            return DB::table('school_years')->count();
        }))->toBe(0);
});
