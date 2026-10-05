<?php

use App\Models\State;
use App\Models\Subject;
use Database\Seeders\GeographicCatalogSeeder;
use Database\Seeders\SubjectSeeder;

/**
 * F1-04: states/municipalities/parishes and MPPE subjects are global
 * catalogs — no institution_id, visible to every tenant, not RLS-scoped.
 */
test('geographic and subject catalogs are visible from any institution', function () {
    $this->seed(GeographicCatalogSeeder::class);
    $this->seed(SubjectSeeder::class);

    expect(State::count())->toBe(24);
    expect(Subject::count())->toBeGreaterThan(0);

    $institutionA = tenant();
    expect(State::count())->toBe(24);

    tenant(); // switch tenant context again
    expect(State::count())->toBe(24);
});

test('a state exposes its municipalities and parishes', function () {
    $this->seed(GeographicCatalogSeeder::class);

    $state = State::where('name', 'Zulia')->firstOrFail();

    expect($state->municipalities)->toHaveCount(1);
    expect($state->municipalities->first()->parishes)->toHaveCount(1);
});
