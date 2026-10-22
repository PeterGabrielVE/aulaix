<?php

declare(strict_types=1);

namespace AulaX\Students\Application\GuardianAccount;

use AulaX\IdentityAccess\Application\Account\DeletionBlocker;
use AulaX\IdentityAccess\Application\Port\InstitutionScope;
use AulaX\Students\Application\StudentDirectory;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Tenancy\Application\TenantContext;

/**
 * EST-R08.6 (IDG-R08.3): an account can not be deleted while, in any of its
 * institutions, it is the only legal guardian of an active student. Checked
 * in each institution with that institution active, under its Row Level
 * Security.
 */
final readonly class SoleLegalGuardianBlocker implements DeletionBlocker
{
    public function __construct(
        private InstitutionScope $institutions,
        private GuardianRepository $guardians,
        private StudentDirectory $directory,
    ) {}

    public function reasonsFor(int $accountId): array
    {
        $reasons = [];

        $this->institutions->forEachInstitutionOf($accountId, function (TenantContext $institution) use ($accountId, &$reasons) {
            $guardian = $this->guardians->byAccount($accountId);

            if ($guardian === null) {
                return;
            }

            foreach ($this->directory->soleLegalGuardianOf((int) $guardian->id()?->value) as $student) {
                $reasons[] = "Eres el único representante legal de {$student} en {$institution->name}: pide al plantel que registre otro representante legal antes de eliminar tu cuenta.";
            }
        });

        return $reasons;
    }
}
