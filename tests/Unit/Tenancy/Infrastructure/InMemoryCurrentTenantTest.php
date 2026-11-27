<?php

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;

function demoContext(): TenantContext
{
    return new TenantContext(InstitutionId::fromInt(3), 'Colegio Demo Uno', 'demo');
}

it('has no tenant until one is set', function () {
    $currentTenant = new InMemoryCurrentTenant;

    expect($currentTenant->context())->toBeNull()
        ->and($currentTenant->institutionId())->toBeNull();
});

it('exposes the tenant that was set', function () {
    $currentTenant = new InMemoryCurrentTenant;
    $currentTenant->set(demoContext());

    expect($currentTenant->context())->toEqual(demoContext())
        ->and($currentTenant->institutionId()->value)->toBe(3);
});

it('has no tenant again once cleared', function () {
    $currentTenant = new InMemoryCurrentTenant;
    $currentTenant->set(demoContext());

    $currentTenant->clear();

    expect($currentTenant->context())->toBeNull();
});
