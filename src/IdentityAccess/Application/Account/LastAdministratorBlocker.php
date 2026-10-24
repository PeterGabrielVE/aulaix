<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\IdentityAccess\Application\Port\InstitutionScope;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipStatus;
use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;
use AulaX\Tenancy\Application\TenantContext;

/**
 * IDG-R08.2: an institution must not be left without an active
 * Administrador. Counts the other active Administradores, so it still blocks
 * at the end of the grace period, when the person's own membership is
 * suspended for the deletion (IDG-R08.10).
 */
final readonly class LastAdministratorBlocker implements DeletionBlocker
{
    public function __construct(
        private InstitutionScope $institutions,
        private MembershipRepository $memberships,
        private RoleCatalog $roles,
    ) {}

    public function reasonsFor(int $accountId): array
    {
        $account = AccountId::fromInt($accountId);
        $reasons = [];

        $this->institutions->forEachInstitutionOf($accountId, function (TenantContext $institution) use ($account, &$reasons) {
            $membership = $this->memberships->of($account, $institution->institutionId);

            if ($membership === null || $membership->status === MembershipStatus::Ended || ! $this->roles->rolesOf($account)->has(BaseRole::Administrador)) {
                return;
            }

            $others = $this->roles->activeHoldersOf(BaseRole::Administrador) - ($membership->isActive() ? 1 : 0);

            if ($others < 1) {
                $reasons[] = "Eres el único Administrador activo de {$institution->name}: asigna ese rol a otra persona antes de eliminar tu cuenta.";
            }
        });

        return $reasons;
    }
}
