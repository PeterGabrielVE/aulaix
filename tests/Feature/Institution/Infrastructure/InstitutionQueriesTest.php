<?php

use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\InstitutionAssets\InstitutionAssetsQuery;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionSettingsQuery;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use AulaX\Tenancy\Application\ShowInstitution\ShowInstitutionHandler;
use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Domain\SignatureRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Tenancy\Images;

/**
 * SPEC-006: what the institution screen shows, and the public queries other
 * modules read: type and levels (SPEC-008) and the images (SPEC-012).
 */
beforeEach(function () {
    Storage::fake(config('tenancy.files_disk'));
});

function withSignature(SignatureRole $role, string $name): string
{
    $path = app(InstitutionFiles::class)->store(AssetKind::signatureOf($role), ImageUpload::fromContents(Images::png()));
    app(SignatureRepository::class)->save(Signature::of($role, $name, 'Cargo', $path));

    return $path;
}

it('shows the open institution with its location, options and signatures', function () {
    $state = DB::table('states')->insertGetId(['name' => 'Zulia', 'code' => 'Q-ZU']);
    $municipality = DB::table('municipalities')->insertGetId(['state_id' => $state, 'name' => 'Maracaibo', 'code' => 'Q-ZU-01']);
    $parish = DB::table('parishes')->insertGetId(['municipality_id' => $municipality, 'name' => 'Chiquinquirá', 'code' => 'Q-ZU-01-01']);
    tenant(['name' => 'Colegio Norte', 'rif' => 'J-1', 'parish_id' => $parish, 'type' => 'privada', 'education_levels' => ['primaria']]);
    withSignature(SignatureRole::Secretary, 'Eva Ruiz');

    $view = app(ShowInstitutionHandler::class)(includeSignatures: true);

    expect($view->name)->toBe('Colegio Norte')
        ->and($view->rif)->toBe('J-1')
        ->and($view->type)->toBe('privada')
        ->and($view->levels)->toBe(['primaria'])
        ->and($view->location['parish_id'])->toBe($parish)
        ->and(array_column($view->parishes, 'id'))->toContain($parish)
        ->and(array_column($view->levelOptions, 'code'))->toBe(['inicial', 'primaria', 'media_general'])
        ->and($view->hasLogo)->toBeFalse()
        ->and(array_map(fn ($signature) => [$signature->role, $signature->signerName], $view->signatures))->toBe([['secretary', 'Eva Ruiz']]);
});

it('leaves the signatures out for whoever only views the institution', function () {
    tenant();
    withSignature(SignatureRole::Director, 'Ana Pérez');

    expect(app(ShowInstitutionHandler::class)(includeSignatures: false)->signatures)->toBeNull();
});

it('tells other modules the type and levels of the open institution, without inventing them', function () {
    tenant(['type' => 'subvencionada', 'education_levels' => ['inicial', 'media_general']]);
    $settings = app(InstitutionSettingsQuery::class)();

    tenant();
    $unset = app(InstitutionSettingsQuery::class)();

    expect($settings->type)->toBe('subvencionada')
        ->and($settings->levels)->toBe(['inicial', 'media_general'])
        ->and($unset->type)->toBeNull()
        ->and($unset->levels)->toBe([]);
});

it('hands over the logo and the signatures of the open institution only', function () {
    tenant();
    withSignature(SignatureRole::Director, 'Ana Pérez');
    $assets = app(InstitutionAssetsQuery::class);

    expect($assets(AssetKind::DirectorSignature)->contents)->toBe(Images::png())
        ->and($assets(AssetKind::SecretarySignature))->toBeNull()
        ->and($assets(AssetKind::Logo))->toBeNull();

    tenant();

    expect($assets(AssetKind::DirectorSignature))->toBeNull();
});

test('the type and levels answer within 10 ms (p95)', function () {
    tenant(['type' => 'publica', 'education_levels' => ['primaria']]);
    $query = app(InstitutionSettingsQuery::class);

    $timings = [];
    foreach (range(1, 200) as $call) {
        $began = hrtime(true);
        $query();
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    expect($timings[(int) floor(count($timings) * 0.95)])->toBeLessThan(10.0);
})->group('perf');
