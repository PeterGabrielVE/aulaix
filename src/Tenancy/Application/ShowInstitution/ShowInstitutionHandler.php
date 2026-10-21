<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ShowInstitution;

use AulaX\Catalogs\Application\EducationLevels;
use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\OpenInstitution;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\Signature;
use AulaX\Tenancy\Domain\SignatureRepository;
use LogicException;

/**
 * The open institution as its screen shows it (INS-R01.1). The signatures
 * only for whoever manages it (INS-R05.3, R04.7); the caller says so.
 */
final readonly class ShowInstitutionHandler
{
    public function __construct(
        private InstitutionRepository $institutions,
        private SignatureRepository $signatures,
        private CurrentTenant $tenant,
        private EducationLevels $educationLevels,
        private GeographyDirectory $geography,
    ) {}

    public function __invoke(bool $includeSignatures): InstitutionView
    {
        $id = $this->tenant->institutionId() ?? throw new LogicException('The institution screen needs an open institution.');
        $institution = OpenInstitution::load($this->institutions, $id);
        $profile = $institution->profile;
        $location = $profile->parishId === null ? null : $this->geography->locationOf($profile->parishId);

        return new InstitutionView(
            name: $institution->name,
            subdomain: $institution->subdomain->value,
            deaCode: $profile->deaCode,
            rif: $profile->rif,
            location: $location,
            address: $profile->address,
            phone: $profile->phone,
            email: $profile->email,
            type: $institution->type?->value,
            levels: $institution->levels?->codes() ?? [],
            hasLogo: $profile->logoPath !== null,
            levelOptions: $this->educationLevels->all(),
            states: $this->geography->states(),
            municipalities: $location === null ? [] : $this->geography->municipalitiesOf($location['state_id']),
            parishes: $location === null ? [] : $this->geography->parishesOf($location['municipality_id']),
            signatures: $includeSignatures
                ? array_map(fn (Signature $signature) => new SignatureView($signature->role->value, $signature->signerName, $signature->signerTitle), $this->signatures->all())
                : null,
        );
    }
}
