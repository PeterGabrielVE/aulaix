<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\InstitutionSettings;

use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Domain\InstitutionRepository;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Domain\SignatureRole;
use LogicException;

/**
 * Public read query of the open institution's profile and signature metadata
 * for SPEC-012 (A-06, DOC-R05.1 – R05.3).
 */
final readonly class InstitutionProfileQuery
{
    public function __construct(
        private InstitutionRepository $institutions,
        private SignatureRepository $signatures,
        private CurrentTenant $tenant,
        private GeographyDirectory $geography,
    ) {}

    public function __invoke(): InstitutionProfileView
    {
        $id = $this->tenant->institutionId() ?? throw new LogicException('InstitutionProfileQuery requires an active institution.');
        $institution = $this->institutions->byId($id) ?? throw new LogicException('Active institution not found.');

        $director = $this->signatures->of(SignatureRole::Director);
        $secretary = $this->signatures->of(SignatureRole::Secretary);

        $missingNotices = [];

        if ($institution->profile->deaCode === null || trim($institution->profile->deaCode) === '') {
            $missingNotices[] = 'Falta configurar el código DEA del plantel.';
        }

        if ($director === null) {
            $missingNotices[] = 'Falta cargar la firma del Director (se imprimirá línea de firma en blanco).';
        }

        return new InstitutionProfileView(
            name: $institution->name,
            subdomain: $institution->subdomain->value,
            deaCode: $institution->profile->deaCode,
            rif: $institution->profile->rif,
            location: $this->resolveLocation($institution->profile->parishId),
            address: $institution->profile->address,
            phone: $institution->profile->phone,
            email: $institution->profile->email,
            directorSignerName: $director?->signerName,
            directorSignerTitle: $director?->signerTitle,
            hasDirectorSignature: $director !== null,
            secretarySignerName: $secretary?->signerName,
            secretarySignerTitle: $secretary?->signerTitle,
            hasSecretarySignature: $secretary !== null,
            missingNotices: $missingNotices,
        );
    }

    private function resolveLocation(?int $parishId): ?string
    {
        if ($parishId === null) {
            return null;
        }

        $location = $this->geography->locationOf($parishId);

        if ($location === null) {
            return null;
        }

        $stateName = $this->nameById($this->geography->states(), $location['state_id']);
        $municipalityName = $this->nameById($this->geography->municipalitiesOf($location['state_id']), $location['municipality_id']);
        $parishName = $this->nameById($this->geography->parishesOf($location['municipality_id']), $location['parish_id']);

        $parts = array_filter([$parishName, $municipalityName, $stateName]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  list<array{id: int, name: string}>  $items
     */
    private function nameById(array $items, int $id): ?string
    {
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $item['name'];
            }
        }

        return null;
    }
}
