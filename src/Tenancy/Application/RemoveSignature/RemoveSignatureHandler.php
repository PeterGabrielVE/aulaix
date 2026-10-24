<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\RemoveSignature;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\OpenInstitution;
use AulaX\Tenancy\Application\Port\InstitutionFiles;
use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use AulaX\Tenancy\Application\SignatureAudit;
use AulaX\Tenancy\Domain\SignatureRepository;
use AulaX\Tenancy\Domain\SignatureRole;

/**
 * The manager removes the signature of a role (INS-R04.5, R06.2). Its file
 * goes once the change is saved.
 */
final readonly class RemoveSignatureHandler
{
    public function __construct(
        private SignatureRepository $signatures,
        private CurrentTenant $tenant,
        private InstitutionPermissions $permissions,
        private InstitutionFiles $files,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(SignatureRole $role): void
    {
        OpenInstitution::forManagement($this->tenant, $this->permissions);

        $removed = $this->transactions->run(function () use ($role) {
            $signature = $this->signatures->of($role);

            if ($signature === null) {
                return null;
            }

            $this->signatures->delete($role);
            $this->audit->record(AuditEvent::of('tenancy.signature.removed', SignatureAudit::subject($role)));

            return $signature->filePath;
        });

        if ($removed !== null) {
            $this->files->delete($removed);
        }
    }
}
