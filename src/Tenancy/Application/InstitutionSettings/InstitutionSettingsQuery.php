<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\InstitutionSettings;

use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Domain\InstitutionRepository;

/**
 * The public API for other modules (SPEC-008 and later): the type and the
 * levels of the open institution, so each decides what it offers
 * (INS-R02.3, R03.3). Nothing is invented for an institution that has not
 * chosen them yet.
 */
final readonly class InstitutionSettingsQuery
{
    public function __construct(
        private InstitutionRepository $institutions,
        private CurrentTenant $tenant,
    ) {}

    public function __invoke(): InstitutionSettingsView
    {
        $id = $this->tenant->institutionId();
        $institution = $id === null ? null : $this->institutions->byId($id);

        return new InstitutionSettingsView($institution?->type?->value, $institution?->levels?->codes() ?? []);
    }
}
