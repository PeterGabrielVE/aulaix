<?php

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\Administration\SetInstitutionStatusHandler;
use AulaX\Tenancy\Application\InstitutionContexts;
use AulaX\Tenancy\Application\InstitutionUnavailable;
use AulaX\Tenancy\Domain\Institution;
use AulaX\Tenancy\Domain\InstitutionProfile;
use AulaX\Tenancy\Domain\InstitutionStatus;
use AulaX\Tenancy\Domain\Subdomain;
use Tests\Fixtures\Tenancy\CountingInstitutionStatusLock;
use Tests\Fixtures\Tenancy\InMemoryInstitutionRepository;

/**
 * SPEC-017 RSP-R08.1, R08.5, R08.6: writableOf() takes the shared status lock
 * and refuses inactive or missing institutions; of() still returns inactive
 * ones for platform reads; SetInstitutionStatusHandler takes the exclusive lock.
 */
beforeEach(function () {
    $this->locks = new CountingInstitutionStatusLock;
    $this->repository = new InMemoryInstitutionRepository(
        Institution::reconstitute(InstitutionId::fromInt(1), 'Colegio Activo', Subdomain::fromString('activo'), InstitutionStatus::Active, InstitutionProfile::empty()),
        Institution::reconstitute(InstitutionId::fromInt(2), 'Colegio Inactivo', Subdomain::fromString('inactivo'), InstitutionStatus::Inactive, InstitutionProfile::empty()),
    );
    $this->contexts = new InstitutionContexts($this->repository, $this->locks);
});

it('takes the shared status lock and returns the context for an active institution', function () {
    $context = $this->contexts->writableOf(InstitutionId::fromInt(1));

    expect($context->institutionId->value)->toBe(1)
        ->and($this->locks->events)->toBe(['shared:1']);
});

it('refuses an inactive or missing institution in writableOf while of still reads it for platform', function () {
    expect(fn () => $this->contexts->writableOf(InstitutionId::fromInt(2)))->toThrow(InstitutionUnavailable::class)
        ->and(fn () => $this->contexts->writableOf(InstitutionId::fromInt(99)))->toThrow(InstitutionUnavailable::class)
        ->and($this->locks->events)->toBe(['shared:2', 'shared:99'])
        ->and($this->contexts->of(InstitutionId::fromInt(2))?->institutionId->value)->toBe(2);
});

it('takes the exclusive status lock before changing an institution status', function () {
    $changed = (new SetInstitutionStatusHandler($this->repository, $this->locks))(InstitutionId::fromInt(1), false);

    expect($changed)->toBeTrue()
        ->and($this->locks->events)->toBe(['exclusive:1'])
        ->and($this->repository->byId(InstitutionId::fromInt(1))?->isActive())->toBeFalse();
});
