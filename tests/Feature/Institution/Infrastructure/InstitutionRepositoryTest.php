<?php

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\InstitutionLevels;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InstitutionType;
use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Domain\SignatureRole;

/**
 * SPEC-006: an institution's settings and signatures go to the database and
 * back.
 */
function institutions(): InstitutionRepository
{
    return app(InstitutionRepository::class);
}

it('stores the name, details, type and levels of an institution', function () {
    $model = tenant(['name' => 'Colegio Viejo']);
    $id = InstitutionId::fromInt($model->id);

    institutions()->save(institutions()->byId($id)
        ->rename('Colegio Nuevo')
        ->changeProfile(InstitutionProfile::fromInput(deaCode: 'OD-7', rif: 'J-1', email: 'info@nuevo.ve'))
        ->changeType(InstitutionType::Privada)
        ->changeLevels(InstitutionLevels::of(['inicial', 'primaria'])));

    $stored = institutions()->byId($id);
    expect($stored->name)->toBe('Colegio Nuevo')
        ->and($stored->profile->deaCode)->toBe('OD-7')
        ->and($stored->type)->toBe(InstitutionType::Privada)
        ->and($stored->levels->codes())->toBe(['inicial', 'primaria'])
        ->and($stored->subdomain->value)->toBe($model->subdomain);
});

it('reads an institution without type nor levels as such', function () {
    $model = tenant();

    expect(institutions()->byId(InstitutionId::fromInt($model->id))->type)->toBeNull()
        ->and(institutions()->byId(InstitutionId::fromInt($model->id))->levels)->toBeNull();
});

it('tells whether another institution uses a DEA code or a RIF', function () {
    $other = tenant(['dea_code' => 'OD-1', 'rif' => 'J-1']);
    $own = tenant(['dea_code' => 'OD-2', 'rif' => 'J-2']);
    $ownId = InstitutionId::fromInt($own->id);

    expect(institutions()->isDeaCodeTaken('OD-1', $ownId))->toBeTrue()
        ->and(institutions()->isDeaCodeTaken('OD-2', $ownId))->toBeFalse()
        ->and(institutions()->isRifTaken('J-1', $ownId))->toBeTrue()
        ->and(institutions()->isRifTaken('J-3', $ownId))->toBeFalse();
});

it('keeps one signature per role in the active institution', function () {
    tenant();
    $signatures = app(SignatureRepository::class);

    $signatures->save(Signature::of(SignatureRole::Director, 'Ana Pérez', 'Directora', 'institutions/1/a.png'));
    $signatures->save(Signature::of(SignatureRole::Director, 'Luis Mora', 'Director', 'institutions/1/b.png'));
    $signatures->save(Signature::of(SignatureRole::Secretary, 'Eva Ruiz', 'Secretaria', 'institutions/1/c.png'));

    expect($signatures->of(SignatureRole::Director)->signerName)->toBe('Luis Mora')
        ->and(count($signatures->all()))->toBe(2);

    $signatures->delete(SignatureRole::Secretary);

    expect($signatures->of(SignatureRole::Secretary))->toBeNull();
});
