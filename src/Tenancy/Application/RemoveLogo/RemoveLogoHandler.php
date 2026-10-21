<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\RemoveLogo;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\InstitutionAudit;
use AulaX\Tenancy\Application\OpenInstitution;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Domain\InstitutionRepository;

/**
 * The manager removes the institution's logo (INS-R04.5, R06.2). Its file
 * goes once the change is saved.
 */
final readonly class RemoveLogoHandler
{
    public function __construct(
        private InstitutionRepository $institutions,
        private CurrentTenant $tenant,
        private InstitutionPermissions $permissions,
        private InstitutionFiles $files,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(): void
    {
        $id = OpenInstitution::forManagement($this->tenant, $this->permissions);

        $removed = $this->transactions->run(function () use ($id) {
            $institution = OpenInstitution::load($this->institutions, $id);

            if ($institution->profile->logoPath === null) {
                return null;
            }

            $this->institutions->save($institution->changeLogo(null));
            $this->audit->record(AuditEvent::of('tenancy.institution.logo_removed', InstitutionAudit::subject($institution)));

            return $institution->profile->logoPath;
        });

        if ($removed !== null) {
            $this->files->delete($removed);
        }
    }
}
