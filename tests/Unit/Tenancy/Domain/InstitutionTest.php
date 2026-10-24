<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionLevels;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\InstitutionType;
use AulaX\Tenancy\Domain\Subdomain;

function institutionWithStatus(InstitutionStatus $status): Institution
{
    return Institution::reconstitute(
        InstitutionId::fromInt(1),
        'Colegio Demo Uno',
        Subdomain::fromString('demo'),
        $status,
        InstitutionProfile::empty(),
    );
}

it('is reachable through its subdomain only while active', function (InstitutionStatus $status, bool $isActive) {
    expect(institutionWithStatus($status)->isActive())->toBe($isActive);
})->with([
    'active' => [InstitutionStatus::Active, true],
    'inactive' => [InstitutionStatus::Inactive, false],
]);

it('requires a name', function () {
    expect(fn () => Institution::reconstitute(
        InstitutionId::fromInt(1),
        '  ',
        Subdomain::fromString('demo'),
        InstitutionStatus::Active,
        InstitutionProfile::empty(),
    ))->toThrow(BusinessRuleViolation::class);
});

it('has every profile field optional', function () {
    $profile = InstitutionProfile::empty();

    expect([$profile->deaCode, $profile->rif, $profile->parishId, $profile->address, $profile->phone, $profile->email, $profile->logoPath])
        ->each->toBeNull();
});

// SPEC-006 — what an institution changes about itself.

it('is renamed, trimmed, and keeps everything else', function () {
    $renamed = institutionWithStatus(InstitutionStatus::Active)->rename('  U.E. Colegio Demo  ');

    expect($renamed->name)->toBe('U.E. Colegio Demo')
        ->and($renamed->subdomain->value)->toBe('demo')
        ->and($renamed->status)->toBe(InstitutionStatus::Active);
});

it('needs a name of 255 characters at most', function (string $name) {
    expect(fn () => institutionWithStatus(InstitutionStatus::Active)->rename($name))->toThrow(BusinessRuleViolation::class);
})->with(['blank' => ['   '], 'too long' => [str_repeat('a', 256)]]);

it('changes its details, type, levels and logo', function () {
    $institution = institutionWithStatus(InstitutionStatus::Active)
        ->changeProfile(InstitutionProfile::fromInput(deaCode: 'OD-1', email: 'info@demo.ve'))
        ->changeType(InstitutionType::Subvencionada)
        ->changeLevels(InstitutionLevels::of(['primaria']))
        ->changeLogo('institutions/1/logo-x.png');

    expect($institution->profile->deaCode)->toBe('OD-1')
        ->and($institution->type)->toBe(InstitutionType::Subvencionada)
        ->and($institution->levels->codes())->toBe(['primaria'])
        ->and($institution->profile->logoPath)->toBe('institutions/1/logo-x.png')
        ->and($institution->changeLogo(null)->profile->logoPath)->toBeNull();
});

it('keeps its logo when its details change', function () {
    $institution = institutionWithStatus(InstitutionStatus::Active)->changeLogo('institutions/1/logo-x.png');

    expect($institution->changeProfile(InstitutionProfile::fromInput(deaCode: 'OD-2'))->profile->logoPath)->toBe('institutions/1/logo-x.png');
});

it('starts with no type nor levels, and offers no way to change its subdomain', function () {
    $institution = institutionWithStatus(InstitutionStatus::Active);

    expect($institution->type)->toBeNull()
        ->and($institution->levels)->toBeNull()
        ->and(array_filter(get_class_methods($institution), fn (string $method) => stripos($method, 'subdomain') !== false))->toBe([]);
});
