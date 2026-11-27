<?php

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\ResolveTenant\InstitutionNotFound;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantHandler;
use AulaX\Tenancy\Application\ResolveTenant\ResolveTenantQuery;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\Subdomain;
use Tests\Fixtures\Tenancy\InMemoryInstitutionRepository;

/**
 * A repository holding the active institutions, keyed by subdomain.
 */
function activeInstitutions(Institution ...$institutions): InstitutionRepository
{
    return new InMemoryInstitutionRepository(...$institutions);
}

function colegioNorte(): Institution
{
    return Institution::reconstitute(
        InstitutionId::fromInt(5),
        'Colegio Norte',
        Subdomain::fromString('norte'),
        InstitutionStatus::Active,
        InstitutionProfile::empty(),
    );
}

it('resolves the active institution of a subdomain into the tenant context', function () {
    $context = (new ResolveTenantHandler(activeInstitutions(colegioNorte())))(new ResolveTenantQuery('norte'));

    expect($context->institutionId->value)->toBe(5)
        ->and($context->name)->toBe('Colegio Norte')
        ->and($context->subdomain)->toBe('norte');
});

it('matches the subdomain regardless of letter case', function () {
    $context = (new ResolveTenantHandler(activeInstitutions(colegioNorte())))(new ResolveTenantQuery('NORTE'));

    expect($context->subdomain)->toBe('norte');
});

it('reports a subdomain with no active institution as not found', function () {
    expect(fn () => (new ResolveTenantHandler(activeInstitutions(colegioNorte())))(new ResolveTenantQuery('sur')))
        ->toThrow(InstitutionNotFound::class);
});

it('reports an invalid or reserved subdomain as not found rather than as a validation error', function (string $subdomain) {
    expect(fn () => (new ResolveTenantHandler(activeInstitutions()))(new ResolveTenantQuery($subdomain)))
        ->toThrow(InstitutionNotFound::class);
})->with(['www', 'colegio_norte', '-norte']);
