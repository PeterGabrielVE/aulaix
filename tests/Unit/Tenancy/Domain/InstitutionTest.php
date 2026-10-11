<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
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
