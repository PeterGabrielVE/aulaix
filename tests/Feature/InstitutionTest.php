<?php

use App\Enums\InstitutionStatus;
use App\Models\Institution;
use AulaX\Catalogs\Infrastructure\Persistence\ParishModel as Parish;
use Database\Seeders\GeographicCatalogSeeder;
use Illuminate\Database\QueryException;

/**
 * F1-01: the institution (tenant) model itself — its status lifecycle and
 * the identifying data of the school.
 */
test('the status is read back as an enum', function () {
    $institution = Institution::factory()->create(['status' => 'inactive']);

    expect($institution->fresh()->status)->toBe(InstitutionStatus::Inactive);
    expect($institution->fresh()->isActive())->toBeFalse();
});

test('the active scope excludes inactive institutions', function () {
    $active = Institution::factory()->create();
    Institution::factory()->inactive()->create();

    expect(Institution::active()->pluck('id')->all())->toBe([$active->id]);
});

test('an institution is located in a parish of the geographic catalog', function () {
    $this->seed(GeographicCatalogSeeder::class);
    $parish = Parish::query()->firstOrFail();

    $institution = Institution::factory()->create(['parish_id' => $parish->id]);

    expect($institution->parish->is($parish))->toBeTrue();
    expect($institution->parish->municipality->state)->not->toBeNull();
});

test('deleting a parish keeps its institutions, without a location', function () {
    $this->seed(GeographicCatalogSeeder::class);
    $parish = Parish::query()->firstOrFail();
    $institution = Institution::factory()->create(['parish_id' => $parish->id]);

    $parish->delete();

    expect($institution->fresh()->parish_id)->toBeNull();
});

test('two institutions can not share a DEA code', function () {
    Institution::factory()->create(['dea_code' => 'OD00099999']);

    expect(fn () => Institution::factory()->create(['dea_code' => 'OD00099999']))
        ->toThrow(QueryException::class);
});

test('two institutions can not share a RIF', function () {
    Institution::factory()->create(['rif' => 'J-12345678-9']);

    expect(fn () => Institution::factory()->create(['rif' => 'J-12345678-9']))
        ->toThrow(QueryException::class);
});

test('the profile fields are optional', function () {
    $institution = Institution::query()->create(['name' => 'Colegio Mínimo', 'subdomain' => 'minimo']);

    expect($institution->fresh())
        ->status->toBe(InstitutionStatus::Active)
        ->dea_code->toBeNull()
        ->parish_id->toBeNull();
});
