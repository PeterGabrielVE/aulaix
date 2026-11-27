<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionDependency;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\InstitutionType;
use AulaX\Tenancy\Domain\Subdomain;

/**
 * SPEC-006 INS-R02.4, R02.5 (through SPEC-013): a public institution says
 * which administration it depends on; the others have none.
 */
function institutionOfType(?InstitutionType $type): Institution
{
    return Institution::reconstitute(
        InstitutionId::fromInt(1),
        'Plantel',
        Subdomain::fromString('plantel'),
        InstitutionStatus::Active,
        InstitutionProfile::empty(),
        $type,
    );
}

it('knows the three dependencies of a public institution', function () {
    expect(array_map(fn (InstitutionDependency $dependency) => $dependency->value, InstitutionDependency::cases()))
        ->toBe(['nacional', 'estadal', 'municipal'])
        ->and(InstitutionDependency::fromInput('estadal'))->toBe(InstitutionDependency::Estadal)
        ->and(InstitutionDependency::fromInput(null))->toBeNull()
        ->and(InstitutionDependency::fromInput(''))->toBeNull();
});

it('refuses any other dependency', function () {
    expect(fn () => InstitutionDependency::fromInput('federal'))
        ->toThrow(BusinessRuleViolation::class, 'Elige nacional, estadal o municipal.');
});

it('keeps the dependency of a public institution, or none', function () {
    $institution = institutionOfType(InstitutionType::Publica)->changeDependency(InstitutionDependency::Municipal);

    expect($institution->dependency)->toBe(InstitutionDependency::Municipal)
        ->and($institution->changeDependency(null)->dependency)->toBeNull();
});

it('refuses a dependency for an institution that is not public', function (?InstitutionType $type) {
    expect(fn () => institutionOfType($type)->changeDependency(InstitutionDependency::Nacional))
        ->toThrow(BusinessRuleViolation::class, 'La dependencia solo aplica a planteles públicos.');
})->with([InstitutionType::Privada, InstitutionType::Subvencionada, null]);

it('drops the dependency when the institution stops being public', function () {
    $institution = institutionOfType(InstitutionType::Publica)->changeDependency(InstitutionDependency::Nacional);

    expect($institution->changeType(InstitutionType::Privada)->dependency)->toBeNull()
        ->and($institution->changeType(InstitutionType::Publica)->dependency)->toBe(InstitutionDependency::Nacional);
});

it('keeps the dependency through the other changes', function () {
    $institution = institutionOfType(InstitutionType::Publica)->changeDependency(InstitutionDependency::Estadal);

    expect($institution->rename('Otro')->changeProfile(InstitutionProfile::empty())->changeLogo('logo.png')->dependency)
        ->toBe(InstitutionDependency::Estadal);
});
