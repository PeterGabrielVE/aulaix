<?php

use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\Subdomain;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;

it('finds the active institution of a subdomain with all its details', function () {
    $stored = InstitutionModel::factory()->create([
        'name' => 'Colegio Norte',
        'subdomain' => 'norte',
        'dea_code' => 'OD00010101',
        'rif' => 'J-00000001-0',
        'address' => 'Av. Principal',
        'phone' => '0212-5550001',
        'email' => 'contacto@norte.test',
        'logo_path' => 'logos/norte.png',
    ]);

    $institution = app(InstitutionRepository::class)->findActiveBySubdomain(Subdomain::fromString('norte'));

    expect($institution->id->value)->toBe($stored->id)
        ->and($institution->name)->toBe('Colegio Norte')
        ->and($institution->subdomain->value)->toBe('norte')
        ->and($institution->status)->toBe(InstitutionStatus::Active)
        ->and($institution->profile->deaCode)->toBe('OD00010101')
        ->and($institution->profile->rif)->toBe('J-00000001-0')
        ->and($institution->profile->address)->toBe('Av. Principal')
        ->and($institution->profile->phone)->toBe('0212-5550001')
        ->and($institution->profile->email)->toBe('contacto@norte.test')
        ->and($institution->profile->logoPath)->toBe('logos/norte.png');
});

it('does not find an inactive institution', function () {
    InstitutionModel::factory()->inactive()->create(['subdomain' => 'cerrado']);

    expect(app(InstitutionRepository::class)->findActiveBySubdomain(Subdomain::fromString('cerrado')))->toBeNull();
});

it('does not find a subdomain no institution uses', function () {
    expect(app(InstitutionRepository::class)->findActiveBySubdomain(Subdomain::fromString('nadie')))->toBeNull();
});
