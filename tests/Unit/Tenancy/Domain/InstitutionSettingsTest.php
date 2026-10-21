<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Tenancy\Domain\InstitutionLevels;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionType;

/**
 * SPEC-006 INS-R01.4, R01.5, R01.8, R02, R03: the settings of an
 * institution, each valid on its own.
 */
function profileViolations(array $input): array
{
    try {
        InstitutionProfile::fromInput(...$input);
    } catch (BusinessRuleViolations $violations) {
        return $violations->messagesByField();
    }

    return [];
}

it('knows the three kinds of institution', function () {
    expect(array_map(fn (InstitutionType $type) => $type->value, InstitutionType::cases()))->toBe(['publica', 'privada', 'subvencionada'])
        ->and(InstitutionType::fromInput('privada'))->toBe(InstitutionType::Privada);
});

it('refuses any other kind', function () {
    expect(fn () => InstitutionType::fromInput('mixta'))->toThrow(BusinessRuleViolation::class, 'Elige si el plantel es público, privado o subvencionado.');
});

it('offers one level at least, each once', function () {
    expect(InstitutionLevels::of(['primaria', 'inicial', 'primaria'])->codes())->toBe(['primaria', 'inicial'])
        ->and(fn () => InstitutionLevels::of([]))->toThrow(BusinessRuleViolation::class, 'Elige al menos un nivel.');
});

it('leaves every institutional field optional', function () {
    $profile = InstitutionProfile::fromInput(deaCode: '', rif: null, parishId: null, address: ' ', phone: '', email: '');

    expect([$profile->deaCode, $profile->rif, $profile->address, $profile->phone, $profile->email])->toBe([null, null, null, null, null]);
});

it('asks no format of the DEA code nor the RIF, only their length', function () {
    expect(profileViolations(['deaCode' => 'OD-0042 x', 'rif' => 'j123']))->toBe([])
        ->and(profileViolations(['deaCode' => str_repeat('1', 21), 'rif' => str_repeat('1', 13)]))->toHaveKeys(['dea_code', 'rif']);
});

it('refuses an email that is not one', function () {
    expect(profileViolations(['email' => 'secretaria@']))->toBe(['email' => ['El correo del plantel no es válido.']])
        ->and(profileViolations(['email' => 'Secretaria@Colegio.ve']))->toBe([]);
});

it('keeps the logo when the institutional data changes', function () {
    $current = new InstitutionProfile(logoPath: 'institutions/1/logo-a.png');

    expect(InstitutionProfile::fromInput(deaCode: 'OD-1', keepingLogoOf: $current)->logoPath)->toBe('institutions/1/logo-a.png');
});
