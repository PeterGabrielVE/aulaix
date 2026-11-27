<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\ChangeLogo;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\Files\AssetKind;
use AulaX\Tenancy\Application\Files\FileReplacement;
use AulaX\Tenancy\Application\Files\ImageUpload;
use AulaX\Tenancy\Application\InstitutionAudit;
use AulaX\Tenancy\Application\OpenInstitution;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Domain\InstitutionRepository;

/**
 * The manager uploads the institution's logo, replacing the previous one
 * (INS-R04.1). The audit log notes the change, never the image (INS-R06.2).
 */
final readonly class ChangeLogoHandler
{
    public function __construct(
        private InstitutionRepository $institutions,
        private CurrentTenant $tenant,
        private InstitutionPermissions $permissions,
        private InstitutionFiles $files,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(ImageUpload $image): void
    {
        $id = OpenInstitution::forManagement($this->tenant, $this->permissions);
        $path = $this->files->store(AssetKind::Logo, $image);

        FileReplacement::commit($this->files, $path, fn () => $this->transactions->run(function () use ($id, $path) {
            $institution = OpenInstitution::load($this->institutions, $id);

            $this->institutions->save($institution->changeLogo($path));
            $this->audit->record(AuditEvent::of('tenancy.institution.logo_changed', InstitutionAudit::subject($institution)));

            return $institution->profile->logoPath;
        }));
    }
}
