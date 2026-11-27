<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC-006 INS-R02.4, R02.5 (through SPEC-013): the database keeps a
 * dependency only for public institutions, and only a known one.
 */
function dependencyMigration(): object
{
    return require database_path('migrations/2026_10_23_202320_add_dependency_to_institutions.php');
}

function setDependency(int $institutionId, ?string $type, ?string $dependency): void
{
    DB::table('institutions')->where('id', $institutionId)->update(['type' => $type, 'dependency' => $dependency]);
}

test('a public institution keeps a known dependency', function () {
    $institution = InstitutionModel::factory()->create();

    setDependency($institution->id, 'publica', 'estadal');

    expect(DB::table('institutions')->where('id', $institution->id)->value('dependency'))->toBe('estadal');
});

test('the database rejects a dependency for a private or subsidised institution, or without a type', function (?string $type) {
    $institution = InstitutionModel::factory()->create();

    expect(fn () => setDependency($institution->id, $type, 'nacional'))->toThrow(QueryException::class);
})->with(['privada', 'subvencionada', null]);

test('the database rejects an unknown dependency', function () {
    $institution = InstitutionModel::factory()->create();

    expect(fn () => setDependency($institution->id, 'publica', 'federal'))->toThrow(QueryException::class);
});

test('the migration rolls back and applies again', function () {
    dependencyMigration()->down();

    expect(Schema::hasColumn('institutions', 'dependency'))->toBeFalse();

    dependencyMigration()->up();

    expect(Schema::hasColumn('institutions', 'dependency'))->toBeTrue();
});
