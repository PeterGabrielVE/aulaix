<?php

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Tenancy\Application\InstitutionGaps\InstitutionGapsQuery;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-013 NAV-R05.6: the mandatory details the open institution still
 * lacks, each with what it holds back.
 */
beforeEach(function () {
    $this->canManage = true;
    $test = $this;
    app()->instance(InstitutionPermissions::class, new class($test) implements InstitutionPermissions
    {
        public function __construct(private readonly object $test) {}

        public function canManage(): bool
        {
            return $this->test->canManage;
        }
    });
});

function directorSignature(int $institutionId): void
{
    DB::table('institution_signatures')->insert([
        'institution_id' => $institutionId, 'role' => 'director', 'signer_name' => 'Ana Rivas', 'signer_title' => 'Directora',
        'file_path' => 'firmas/director.png', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('a new institution lacks its levels, DEA code and Director signature', function () {
    tenant(['dea_code' => null, 'type' => null, 'education_levels' => []]);

    expect(app(InstitutionGapsQuery::class)())->toBe(['levels', 'dea_code', 'director_signature']);
});

test('a private institution also needs its RIF, and a public one its dependency', function (string $type, array $gaps) {
    $institution = tenant(['dea_code' => 'OD-1', 'rif' => null, 'type' => $type, 'education_levels' => ['primaria']]);
    directorSignature($institution->id);

    expect(app(InstitutionGapsQuery::class)())->toBe($gaps);
})->with([
    'private' => ['privada', ['rif']],
    'public' => ['publica', ['dependency']],
    'subsidised' => ['subvencionada', []],
]);

test('a complete institution lacks nothing', function () {
    $institution = tenant(['dea_code' => 'OD-1', 'rif' => 'J-1', 'type' => 'privada', 'education_levels' => ['primaria']]);
    directorSignature($institution->id);

    expect(app(InstitutionGapsQuery::class)())->toBe([]);
});

test('needs gestionar-plantel', function () {
    tenant();
    $this->canManage = false;

    expect(fn () => app(InstitutionGapsQuery::class)())->toThrow(AuthorizationDenied::class);
});
