<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\InstitutionGaps;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\OpenInstitution;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\InstitutionType;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Domain\SignatureRole;

/**
 * The public API for other modules (SPEC-013 NAV-R05.6): the mandatory
 * details the open institution still lacks. Levels hold back its structure,
 * the DEA code and the Director's signature its official documents, the RIF
 * of a private institution its charges, and a public one its dependency.
 * Only who manages the institution asks (gestionar-plantel).
 */
final readonly class InstitutionGapsQuery
{
    public const string LEVELS = 'levels';

    public const string DEA_CODE = 'dea_code';

    public const string DIRECTOR_SIGNATURE = 'director_signature';

    public const string RIF = 'rif';

    public const string DEPENDENCY = 'dependency';

    public function __construct(
        private InstitutionRepository $institutions,
        private SignatureRepository $signatures,
        private CurrentTenant $tenant,
        private InstitutionPermissions $permissions,
    ) {}

    /**
     * @return list<string> In this order: levels, dea_code, director_signature, rif, dependency.
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        $institution = OpenInstitution::load($this->institutions, OpenInstitution::forManagement($this->tenant, $this->permissions));

        return array_keys(array_filter([
            self::LEVELS => $institution->levels === null,
            self::DEA_CODE => $institution->profile->deaCode === null,
            self::DIRECTOR_SIGNATURE => $this->signatures->of(SignatureRole::Director) === null,
            self::RIF => $institution->type === InstitutionType::Privada && $institution->profile->rif === null,
            self::DEPENDENCY => $institution->type === InstitutionType::Publica && $institution->dependency === null,
        ]));
    }
}
